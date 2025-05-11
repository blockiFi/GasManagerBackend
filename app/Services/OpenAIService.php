<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenAIService
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY');
    }

    public function analyzeImage($imagePath)
    {
        $image = base64_encode(file_get_contents($imagePath));

        $response = Http::withToken($this->apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4.1',
                // 'model' => 'gpt-4.1-nano',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                           







                            // ['type' => 'text', 'text' => 'You are an expert in Optical Character Recognition (OCR), and your task is to extract accurate, reliable, and complete numeric information from an image of a gas machine. Focus strictly on the brightest visible section of the image, which always contains two numeric values in this order: the first is the KG (kilograms) and the second is the Amount (currency). Both numbers must be extracted with absolute precision—no digit should be missed, altered, or misread, and decimal points must be correctly placed. Return the extracted result exactly in this format: [1980.150, 535750.22] — as a flat array with no quotes, no newlines, and no extra characters. Do not include brackets inside strings, do not return values as separate strings or with formatting artifacts. Your output must match the format [KG, AMOUNT] within the [...] wrapper, with both numbers as raw numeric values, not strings. If the uploaded image does not match the described gas machine structure or does not contain the clearly visible two-part numeric data, return null—nothing else.'],    
                            // ['type' => 'text', 'text' => "You are a machine vision model. Your task is to read an image of a gas machine and extract two numbers from the brightest part of the image. The bright section always shows two numbers: the first number is for KG (kilograms), and the second number is for Amount (money). Both numbers must be extracted exactly as they appear, including all digits and decimal points. Do not skip or change anything. Return the result in this exact format: [1980.150, 535750.22]. Do not include any extra text, quotes, newlines, or strings—just the two numbers inside square brackets, separated by a comma. If the image is not from a gas machine or does not contain two valid numbers in the bright section, return null and nothing else."],    
                            ['type' => 'text', 'text' => "You are an OCR model. Your task is to read an image of a gas machine display and extract two numeric values from the brightest part of the image. The top number is always the KG (kilograms), and the bottom number is the Amount (money). Both numbers always contain exactly two digits after the decimal point. For example: 18584.27 and 218885.22.

                                You must read every digit accurately and include the decimal point. If the decimal is faint or missing, you must infer its correct position so that the number ends with exactly two decimal digits. For example, if the detected number is 1858427, convert it to 18584.27.

                                Be very careful not to confuse similar-looking digits. Most importantly:

                                Do not confuse 8 with 0

                                Do not confuse 6 with 5

                                Do not confuse 1 with 7

                                If any digit is unclear or partially hidden, use the overall shape and spacing to identify the correct number.

                                You must return the result in this exact format: [18584.27, 218885.22]

                                Do not add any quotes, extra text, newlines, or symbols. Only return the two numbers inside square brackets, separated by a comma.

                                If the image is invalid or does not contain two valid numeric values in the bright section, return: null

                                Your output must be precise. Both numbers must include all correct digits and end with exactly two decimal places. Do not skip, merge, or misread any characters. Return only the array or null — nothing else."],    
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . $image]],
                        ],
                    ]
                ],
                'max_tokens' => 300,
            ]);

        return $response->json();
    }
}
