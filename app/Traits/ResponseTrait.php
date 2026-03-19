<?php

namespace App\Traits;

trait ResponseTrait
{
    public function apiSuccess(string $message = 'Request successful', array|object|null $data = null,  int $statusCode = 200)
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
            'success' => true,
        ], $statusCode);
    }

    public function apiError(string $message = 'An error occurred', int $statusCode = 422, $errors = null)
    {
        return response()->json([
            'message' => $message,
            'errors' => $errors,
            'success' => false,
        ], $statusCode);
    }
    private function colorNameToHex(?string $colorName): ?string
    {
        if (empty($colorName)) {
            return null;
        }
        $colors = [
            'black' => '#000000',
            'white' => '#FFFFFF',
            'red' => '#FF0000',
            'green' => '#008000',
            'blue' => '#0000FF',
            'yellow' => '#FFFF00',
            'cyan' => '#00FFFF',
            'magenta' => '#FF00FF',
            'gray' => '#808080',
            'orange' => '#FFA500',
            'pink' => '#FFC0CB',
            'purple' => '#800080',
            'brown' => '#A52A2A',
            'skyblue' => '#87CEEB',
            // Add more as needed
        ];

        $key = strtolower(trim($colorName));
        return $colors[$key] ?? null; // returns null if color not found
    }
}
