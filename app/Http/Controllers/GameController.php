<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stage;
use App\Models\Story;

class GameController extends Controller
{
    /**
     * Get all stages with their stories
     */
    public function getStages()
    {
        $stages = Stage::with('stories')->orderBy('stage_number')->get();
        
        return response()->json([
            'success' => true,
            'stages' => $stages
        ]);
    }

    /**
     * Get a specific stage with its stories
     */
    public function getStage($stageNumber)
    {
        $stage = Stage::with('stories')
                     ->where('stage_number', $stageNumber)
                     ->first();
        
        if (!$stage) {
            return response()->json([
                'success' => false,
                'message' => 'Stage not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'stage' => $stage
        ]);
    }

    /**
     * Get stories for a specific stage in random order (for game display)
     */
    public function getStageStories($stageNumber)
    {
        $stage = Stage::where('stage_number', $stageNumber)->first();
        
        if (!$stage) {
            return response()->json([
                'success' => false,
                'message' => 'Stage not found'
            ], 404);
        }
        
        $stories = Story::where('stage_id', $stage->id)
                       ->inRandomOrder()
                       ->get();
        
        return response()->json([
            'success' => true,
            'stage' => $stage,
            'stories' => $stories
        ]);
    }

    /**
     * Check if a story answer is correct
     */
    public function checkAnswer(Request $request)
    {
        $request->validate([
            'stage_number' => 'required|integer',
            'story_id' => 'required|integer'
        ]);
        
        $stage = Stage::where('stage_number', $request->stage_number)->first();
        
        if (!$stage) {
            return response()->json([
                'success' => false,
                'message' => 'Stage not found'
            ], 404);
        }
        
        $story = Story::where('id', $request->story_id)
                     ->where('stage_id', $stage->id)
                     ->first();
        
        if (!$story) {
            return response()->json([
                'success' => false,
                'message' => 'Story not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'is_correct' => $story->is_correct,
            'points' => $story->is_correct ? $stage->points : 0
        ]);
    }

    /**
     * Get total number of stages
     */
    public function getStageCount()
    {
        $count = Stage::count();
        
        return response()->json([
            'success' => true,
            'total_stages' => $count
        ]);
    }
} 