<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OpenAIService;
use Validator;
use Illuminate\Support\Str;
class ImageAnalysisController extends Controller
{
    protected $openAIService;

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    public function upload(Request $request)
    {
        
        $validator = Validator::make($request->all(), [
            "image" => "required|image"
      ]);
    
      if ($validator->fails()) {
    
           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }
        $imagePath = $request->file('image')->getRealPath();

        $response = $this->openAIService->analyzeImage($imagePath);
        $text = $response['choices'][0]['message']['content'];
        if(Str::contains($text, 'null')){
            $res['code'] = 400;
            $res['errors'] = ['Image does not contain the required numeric data'];
            return response()->json($res ,400);
        }
        $array = json_decode($text , true);
        
        return $res = [
            'code' => 200,
            'message' => 'Image analyzed successfully',
            'data' => $array
        ];

        return response()->json($res,200);

    }
    
    
}
