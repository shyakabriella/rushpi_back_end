<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'service_type' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Service::query()
            ->where('service_type', 'paint')
            ->where('is_active', true)
            ->where('status', 'active');

        $search = trim((string) ($validated['q'] ?? ''));

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('paint_type', 'like', "%{$search}%")
                    ->orWhere('brand_name', 'like', "%{$search}%")
                    ->orWhere('color_name', 'like', "%{$search}%");
            });
        }

        $services = $query
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 50)
            ->withQueryString();

        $services->through(
            fn (Service $service): array =>
                $this->serviceData($service)
        );

        return response()->json([
            'success' => true,
            'message' => 'Available paints retrieved successfully.',
            'data' => $services,
        ]);
    }

    public function show(Service $service): JsonResponse
    {
        $this->ensurePublic($service);

        return response()->json([
            'success' => true,
            'data' => $this->serviceData($service),
        ]);
    }

    public function quote(
        Request $request,
        Service $service
    ): JsonResponse {
        $this->ensurePublic($service);

        $data = $request->validate([
            'mode' => [
                'required',
                Rule::in(['quantity', 'amount']),
            ],

            'unit' => [
                'required',
                Rule::in(Service::UNITS),
            ],

            'quantity' => [
                'nullable',
                'numeric',
                'gt:0',
            ],

            'amount_rwf' => [
                'nullable',
                'numeric',
                'gt:0',
            ],
        ]);

        try {
            $quote = $data['mode'] === 'amount'
                ? $service->quoteAmount(
                    (float) ($data['amount_rwf'] ?? 0),
                    $data['unit']
                )
                : $service->quote(
                    (float) ($data['quantity'] ?? 0),
                    $data['unit']
                );

            return response()->json([
                'success' => true,
                'data' => [
                    'service' => $this->serviceData($service),
                    'quote' => $quote,
                ],
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    private function ensurePublic(Service $service): void
    {
        abort_unless(
            $service->service_type === 'paint'
            && $service->is_active
            && $service->status === 'active',
            404
        );
    }

    private function serviceData(Service $service): array
    {
        return [
            'public_id' => (string) $service->public_id,
            'name' => $service->name,
            'paint_type' => $service->paint_type,
            'brand_name' => $service->brand_name,
            'color_name' => $service->color_name,
            'description' => $service->description,

            'reference_quantity' => $service->reference_quantity,
            'reference_unit' => $service->reference_unit,
            'reference_price_rwf' => $service->reference_price_rwf,

            'density_kg_per_l' => $service->density_kg_per_l,

            'allow_volume_sale' => $service->allow_volume_sale,
            'allow_weight_sale' => $service->allow_weight_sale,
            'allow_amount_sale' => $service->allow_amount_sale,

            'stock_quantity' => $service->stock_quantity,
            'stock_unit' => $service->stock_unit,

            'image_url' => $service->image_url,
            'reference_label' => $service->reference_label,
        ];
    }
}
