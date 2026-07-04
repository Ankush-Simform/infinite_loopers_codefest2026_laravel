<?php

namespace Database\Seeders;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        foreach (User::all() as $user) {

            $session = ChatSession::create([
                'user_id' => $user->id,
                'title' => 'Health Assistant',
                'last_message_at' => now(),
            ]);

            ChatMessage::create([
                'chat_session_id' => $session->id,
                'role' => 'user',
                'content' => 'Explain my blood report.',
            ]);

            ChatMessage::create([
                'chat_session_id' => $session->id,
                'role' => 'assistant',
                'content' => 'Your blood report appears normal.',
            ]);
        }
    }
}
