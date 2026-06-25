namespace App\Services;

use Illuminate\Support\Facades\Http;

class MachineLearningService
{
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.ml.url','http://localhost:5000');
    }

    public function classifyTopic($title, $content)
    {
        $response = Http::post("{$this->baseUrl}/classify", [
            'title' => $title,
            'content' => $content,
        ]);

        if ($response->successful()) {
            return $response->json('category');
        }
        return 'unclassified';
    }
    public  function getRecommendations($userId)
    {
        $response = Http::post("{$this->baseUrl}/recommend", [
            'user_id' => $userId,
            ]);
        if ($response->successful()) {
            return $response->json('recomend_topic_ids');
        }
        return [];
        
    }
}