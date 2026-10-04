<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ForgotPasswordController extends Controller
{
    public function show(): View
    {
        return view('auth.forgot-password');
    }

    public function getQuestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:4', 'max:50'],
        ]);

        $user = User::where('username', trim($validated['username']))->first();
        $answers = $user?->securityAnswers()->orderBy('id')->limit(3)->get() ?? collect();

        if ($answers->count() < 3) {
            return response()->json(['success' => false, 'message' => 'Unable to verify this account. Check the username and try again.']);
        }

        return response()->json(['success' => true, 'questions' => $answers->pluck('question')]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:4', 'max:50'],
            'answer1' => ['required', 'string', 'max:255'],
            'answer2' => ['required', 'string', 'max:255'],
            'answer3' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = User::where('username', trim($validated['username']))->first();
        $answers = $user?->securityAnswers()->orderBy('id')->limit(3)->get() ?? collect();

        if ($answers->count() < 3) {
            return response()->json(['success' => false, 'message' => 'One or more answers are incorrect.']);
        }

        $allCorrect = Hash::check(strtolower(trim($validated['answer1'])), $answers[0]->answer_hash)
            && Hash::check(strtolower(trim($validated['answer2'])), $answers[1]->answer_hash)
            && Hash::check(strtolower(trim($validated['answer3'])), $answers[2]->answer_hash);

        if (! $allCorrect) {
            return response()->json(['success' => false, 'message' => 'One or more answers are incorrect.']);
        }

        $user->update(['password' => $validated['password']]);

        return response()->json(['success' => true, 'message' => 'Password reset successfully. You can now log in with your new password.']);
    }
}
