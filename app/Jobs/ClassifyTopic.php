<?php

namespace App\Jobs;

use App\Models\Topic;
use App\Services\MachineLearningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ClassifyTopic implements ShouldQueue
{
    use Queueable,Dispachable;

    protected $topic;

    
      //Create a new job instance.
     
    public function __construct(Topic $topic)
    {
        $this->topic = $topic;
    }

    
      //Execute the job.
     
    public function handle(MachineLearningService $mlService) 
    {
        $category = $mlService->classify($this->topic->title, $this->topic->content);
        $this->topic->update(['predicted_category' => $category]);
    }
}
