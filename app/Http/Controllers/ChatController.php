<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\User;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function getMessages(Request $request)
    {
        $user = $request->user();
        $receiverId = $request->input('receiver_id');
        $projectId = $request->input('project_id');
        $lastMessageId = $request->input('last_message_id', 0);

        if ($projectId) {
            // Project chat (group chat)
            $query = ChatMessage::where('project_id', $projectId)
                ->where('id', '>', $lastMessageId)
                ->with('sender', 'project')
                ->orderBy('created_at', 'asc');

            $messages = $query->get();

            // Mark as read for this user in project chat
            ChatMessage::where('project_id', $projectId)
                ->where('sender_id', '!=', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        } else {
            // Private chat
            $query = ChatMessage::where(function ($q) use ($user, $receiverId) {
                $q->where(function ($q2) use ($user, $receiverId) {
                    $q2->where('sender_id', $user->id)
                       ->where('receiver_id', $receiverId);
                })->orWhere(function ($q2) use ($user, $receiverId) {
                    $q2->where('sender_id', $receiverId)
                       ->where('receiver_id', $user->id);
                });
            })
            ->whereNull('project_id')
            ->where('id', '>', $lastMessageId)
            ->with('sender', 'receiver')
            ->orderBy('created_at', 'asc');

            $messages = $query->get();

            // Mark as read
            ChatMessage::where('receiver_id', $user->id)
                ->where('sender_id', $receiverId)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return response()->json($messages);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'nullable|exists:users,id',
            'project_id' => 'nullable|exists:projects,id',
            'message' => 'required|string|max:5000',
        ]);

        // If project_id is provided, it's a project chat (group chat)
        // If receiver_id is provided, it's a private chat
        if ($request->project_id) {
            // Verify user is part of the project
            $project = Project::with('teams')->find($request->project_id);
            if (!$project) {
                return response()->json(['message' => 'Project not found'], 404);
            }

            $user = $request->user();
            $team = \App\Models\Team::where('user_id', $user->id)->first();
            
            // Check if user is assigned to project (either as team member or BD)
            $isAssigned = $project->teams->contains('id', $team?->id) || 
                         $project->assigned_bd === $user->id ||
                         $user->role === 'Admin';

            if (!$isAssigned) {
                return response()->json(['message' => 'You are not part of this project'], 403);
            }

            $message = ChatMessage::create([
                'sender_id' => $user->id,
                'receiver_id' => null,
                'project_id' => $request->project_id,
                'message' => $request->message,
                'type' => 'group',
                'is_read' => false,
            ]);
        } else {
            // Private chat
            $message = ChatMessage::create([
                'sender_id' => $request->user()->id,
                'receiver_id' => $request->receiver_id,
                'project_id' => null,
                'message' => $request->message,
                'type' => 'private',
                'is_read' => false,
            ]);
        }

        return response()->json($message->load('sender', 'receiver', 'project'), 201);
    }

    public function getConversations(Request $request)
    {
        $user = $request->user();
        $team = \App\Models\Team::where('user_id', $user->id)->first();

        // Get private conversations
        $privateConversations = ChatMessage::select(
            DB::raw('CASE 
                WHEN sender_id = ' . $user->id . ' THEN receiver_id 
                ELSE sender_id 
            END as other_user_id'),
            DB::raw('MAX(created_at) as last_message_time'),
            DB::raw('MAX(id) as last_message_id')
        )
        ->whereNull('project_id')
        ->where(function ($q) use ($user) {
            $q->where('sender_id', $user->id)
              ->orWhere('receiver_id', $user->id);
        })
        ->groupBy(DB::raw('CASE 
            WHEN sender_id = ' . $user->id . ' THEN receiver_id 
            ELSE sender_id 
        END'))
        ->orderBy('last_message_time', 'desc')
        ->get();

        $conversationData = [];
        
        // Add private conversations
        foreach ($privateConversations as $conv) {
            $otherUser = User::find($conv->other_user_id);
            if (!$otherUser) continue;
            
            $lastMessage = ChatMessage::find($conv->last_message_id);
            $unreadCount = ChatMessage::where('sender_id', $conv->other_user_id)
                ->where('receiver_id', $user->id)
                ->whereNull('project_id')
                ->where('is_read', false)
                ->count();

            $conversationData[] = [
                'type' => 'private',
                'user' => $otherUser,
                'project' => null,
                'last_message' => $lastMessage,
                'unread_count' => $unreadCount,
                'last_message_time' => $conv->last_message_time,
            ];
        }

        // Get project conversations (group chats)
        if ($team) {
            $projectIds = $team->projects()->pluck('projects.id');
            
            // Also include projects where user is assigned as BD
            $bdProjectIds = Project::where('assigned_bd', $user->id)->pluck('id');
            $allProjectIds = $projectIds->merge($bdProjectIds)->unique();

            $projectConversations = ChatMessage::select(
                'project_id',
                DB::raw('MAX(created_at) as last_message_time'),
                DB::raw('MAX(id) as last_message_id')
            )
            ->whereIn('project_id', $allProjectIds)
            ->whereNotNull('project_id')
            ->groupBy('project_id')
            ->orderBy('last_message_time', 'desc')
            ->get();

            foreach ($projectConversations as $conv) {
                $project = Project::find($conv->project_id);
                if (!$project) continue;

                $lastMessage = ChatMessage::find($conv->last_message_id);
                $unreadCount = ChatMessage::where('project_id', $conv->project_id)
                    ->where('sender_id', '!=', $user->id)
                    ->where('is_read', false)
                    ->count();

                $conversationData[] = [
                    'type' => 'project',
                    'user' => null,
                    'project' => $project,
                    'last_message' => $lastMessage,
                    'unread_count' => $unreadCount,
                    'last_message_time' => $conv->last_message_time,
                ];
            }
        }

        // Sort all conversations by last message time
        usort($conversationData, function($a, $b) {
            return strtotime($b['last_message_time']) - strtotime($a['last_message_time']);
        });

        return response()->json($conversationData);
    }

    public function getUsers(Request $request)
    {
        $users = User::where('id', '!=', $request->user()->id)
            ->select('id', 'name', 'email', 'role')
            ->get();

        return response()->json($users);
    }

    public function getProjectChats(Request $request)
    {
        $user = $request->user();
        $team = \App\Models\Team::where('user_id', $user->id)->first();

        if (!$team) {
            return response()->json([]);
        }

        // Get all projects the user is part of
        $projectIds = $team->projects()->pluck('projects.id');
        $bdProjectIds = Project::where('assigned_bd', $user->id)->pluck('id');
        $allProjectIds = $projectIds->merge($bdProjectIds)->unique();

        $projects = Project::whereIn('id', $allProjectIds)
            ->with('client', 'teams')
            ->get();

        return response()->json($projects);
    }
}

