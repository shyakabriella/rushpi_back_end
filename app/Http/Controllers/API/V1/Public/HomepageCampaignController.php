<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\HomepageCampaignResource;
use App\Models\HomepageCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomepageCampaignController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {
        $campaigns = HomepageCampaign::query()
            ->currentlyVisible()
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Homepage campaigns retrieved successfully.',
            'data' => HomepageCampaignResource::collection(
                $campaigns
            )->resolve($request),
        ]);
    }
}
