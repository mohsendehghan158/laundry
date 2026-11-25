<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Piece;
use App\Modules\Production\Concerns\ReachesBranches;
use App\Modules\Production\Http\Requests\PhotoRequest;
use App\Modules\Production\Models\Media;
use App\Support\Exceptions\ApiException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhotoController
{
    use ReachesBranches;

    private const int MAX_PER_PIECE = 12;

    private const string DISK = 'local';

    /**
     * Add a photo of a piece
     *
     * At intake, to record damage, or before and after. At most 12 per piece.
     *
     * @response 201 array{data: array{id: int, url: string, uploader_name: string|null, created_at: string}}
     */
    public function store(PhotoRequest $request, Piece $piece): JsonResponse
    {
        abort_unless($this->reachesPiece($request->actor(), $piece), 404);

        if ($piece->photos()->count() >= self::MAX_PER_PIECE) {
            throw ApiException::conflict('photo_limit', ['max' => self::MAX_PER_PIECE]);
        }

        $file = $request->file('photo');
        abort_if($file === null, 422);
        $path = $file->storeAs("pieces/{$piece->organization_id}/{$piece->id}", Str::ulid().'.'.$file->extension(), self::DISK);

        $media = Media::query()->create([
            'organization_id' => $piece->organization_id,
            'piece_id' => $piece->id,
            'disk' => self::DISK,
            'path' => (string) $path,
            'mime' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by' => $request->actor()->id,
        ]);

        return response()->json(['data' => self::describe($media->load('uploader'))], 201);
    }

    /**
     * Show a photo
     *
     * The image itself, for those who can see the piece.
     */
    public function show(Media $media, #[CurrentUser] User $actor): StreamedResponse
    {
        abort_unless(($actor->can('production.view') || $actor->can('orders.view')) && $this->reachesPiece($actor, $media->piece), 404);

        return Storage::disk($media->disk)->response($media->path, null, [
            'Content-Type' => $media->mime,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Delete a photo
     *
     * By whoever took it, or a workshop manager.
     */
    public function destroy(Media $media, #[CurrentUser] User $actor): Response
    {
        abort_unless($this->reachesPiece($actor, $media->piece), 404);
        abort_unless($media->uploaded_by === $actor->id || $actor->can('production.manage'), 403);

        Storage::disk($media->disk)->delete($media->path);
        $media->delete();

        return response()->noContent();
    }

    /**
     * @return array{id: int, url: string, uploader_name: string|null, created_at: string}
     */
    public static function describe(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => '/api/v1/media/'.$media->id,
            'uploader_name' => $media->uploader?->name,
            'created_at' => $media->created_at->toIso8601String(),
        ];
    }
}
