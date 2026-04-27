<?php

namespace App\Http\Controllers;

use App\Services\OpenAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImageAnalysisController extends Controller
{
    public function __construct(protected OpenAIService $openAIService) {}

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        if ($validator->fails()) {
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();

            return response()->json($response, 400);
        }
        $imagePath = $request->file('image')->getRealPath();

        $response = $this->openAIService->analyzeImage($imagePath);
        $text = $response['choices'][0]['message']['content'];
        if (Str::contains($text, 'null')) {
            $res['code'] = 400;
            $res['errors'] = ['Image does not contain the required numeric data'];

            return response()->json($res, 400);
        }
        $array = json_decode($text, true);

        return response()->json([
            'code' => 200,
            'message' => 'Image analyzed successfully',
            'data' => $array,
        ], 200);
    }
}
