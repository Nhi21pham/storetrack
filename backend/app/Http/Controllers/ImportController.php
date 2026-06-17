<?php

namespace App\Http\Controllers;

use App\Exceptions\AppException;
use App\Imports\ImporterRegistry;
use App\Models\Import;
use App\Models\Store;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    public function __construct(
        private ImportService $importService,
        private ImporterRegistry $registry,
    ) {}

    public function unitsTemplate(Request $request, int $storeId): BinaryFileResponse|JsonResponse
    {
        return $this->template($request, $storeId, 'units');
    }

    public function unitsPreview(Request $request, int $storeId): JsonResponse
    {
        return $this->preview($request, $storeId, 'units');
    }

    public function unitsStart(Request $request, int $storeId): JsonResponse
    {
        return $this->start($request, $storeId, 'units', $this->storeName($storeId));
    }

    public function tagsTemplate(Request $request, int $storeId): BinaryFileResponse|JsonResponse
    {
        return $this->template($request, $storeId, 'tags');
    }

    public function tagsPreview(Request $request, int $storeId): JsonResponse
    {
        return $this->preview($request, $storeId, 'tags');
    }

    public function tagsStart(Request $request, int $storeId): JsonResponse
    {
        return $this->start($request, $storeId, 'tags', $this->storeName($storeId));
    }

    public function status(Request $request, int $importId): JsonResponse
    {
        try {
            $import = $this->importService->getForUser($request->user(), $importId);

            return $this->importResponse($import);
        } catch (AppException $e) {
            return $this->appExceptionResponse($e);
        }
    }

    public function storeHistory(Request $request, int $storeId): JsonResponse
    {
        $type = $request->query('type');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        try {
            $result = $this->importService->history(
                $request->user(),
                $storeId,
                is_string($type) && $type !== '' ? $type : null,
                is_string($startDate) && $startDate !== '' ? $startDate : null,
                is_string($endDate) && $endDate !== '' ? $endDate : null,
                (int) $request->query('per_page', 20),
            );

            return response()->json($result);
        } catch (AppException $e) {
            return $this->appExceptionResponse($e);
        }
    }

    public function storeHistoryDetail(Request $request, int $storeId, int $importId): JsonResponse
    {
        try {
            $import = $this->importService->getForStore($request->user(), $storeId, $importId);

            return $this->importResponse($import);
        } catch (AppException $e) {
            return $this->appExceptionResponse($e);
        }
    }

    private function template(Request $request, int $scopeId, string $type): BinaryFileResponse|JsonResponse
    {
        try {
            $importer = $this->registry->for($type);
            $template = $this->importService->template($request->user(), $scopeId, $importer);

            return Excel::download($template, $type.'-import-template.xlsx');
        } catch (AppException $e) {
            return $this->appExceptionResponse($e);
        }
    }

    private function preview(Request $request, int $scopeId, string $type): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        try {
            $importer = $this->registry->for($type);
            $data = $this->importService->preview(
                $request->user(),
                $scopeId,
                $importer,
                $request->file('file'),
            );

            return response()->json($data, 200);
        } catch (AppException $e) {
            return $this->appExceptionResponse($e);
        }
    }

    private function start(Request $request, int $scopeId, string $type, ?string $scopeName): JsonResponse
    {
        $request->validate([
            'rows'              => ['required', 'array', 'min:1'],
            'original_filename' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $importer = $this->registry->for($type);
            $import = $this->importService->queueImport(
                $request->user(),
                $scopeId,
                $scopeName,
                $importer,
                $request->input('rows', []),
                (string) $request->input('original_filename', ''),
            );

            return $this->importResponse($import, 202);
        } catch (AppException $e) {
            return $this->appExceptionResponse($e);
        }
    }

    private function storeName(int $storeId): ?string
    {
        return Store::find($storeId)?->name;
    }

    private function importResponse(Import $import, int $status = 200): JsonResponse
    {
        $metadata = $import->metadata ?? [];

        return response()->json([
            'id'                => $import->id,
            'type'              => $import->type,
            'status'            => $import->status,
            'original_filename' => $import->original_filename,
            'total_rows'        => $import->total_rows,
            'processed_count'   => $import->processed_count,
            'created_count'     => $import->created_count,
            'skipped_count'     => $import->skipped_count,
            'failed_count'      => $import->failed_count,
            'error_message'     => $import->error_message,
            'results'           => $metadata['results'] ?? [],
            'completed_at'      => optional($import->completed_at)->toIso8601String(),
            'created_at'        => optional($import->created_at)->toIso8601String(),
        ], $status);
    }

    private function appExceptionResponse(AppException $e): JsonResponse
    {
        return response()->json([
            'code' => $e->getErrorCode()->value,
            'message' => $e->getMessage(),
        ], $e->getStatusCode());
    }
}
