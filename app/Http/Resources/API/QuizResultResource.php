<?php

namespace App\Http\Resources\API;

use App\Models\QuizQuestionAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizResultResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        $result = json_decode($this->resource, true);

        return array_map(function ($data) {
            // The submitted answer row — title gives us the user-readable
            // option text, question_id lets us look up the right answer.
            $answer = isset($data['answer']) ? QuizQuestionAnswer::select('id', 'question_id', 'title')
                ->with('question:id,title')
                ->find($data['answer']) : null;

            // Look up the correct answer for the same question so we can
            // show "Correct answer: X" alongside the user's pick on the
            // mobile attempt-review screen. Done as a second small query
            // rather than eager-loading on `question.answers` because the
            // result set is bounded by question count (~1-30) and we want
            // to keep the resource readable.
            $correctAnswerTitle = 'N/A';
            if ($answer?->question_id) {
                $correctAnswerTitle = QuizQuestionAnswer::where('question_id', $answer->question_id)
                    ->where('correct', 1)
                    ->value('title') ?? 'N/A';
            }

            return [
                'question'       => $answer && $answer?->question ? (string) $answer?->question?->title : 'N/A',
                'answer'         => $answer ? (string) $answer?->title : 'N/A',
                'correct_answer' => (string) $correctAnswerTitle,
                'correct'        => (bool) $data['correct'],
            ];
        }, $result);
    }
}
