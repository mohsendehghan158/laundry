<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;

/** One tag or a batch scanned onto (or off) a transfer or shipment. */
class TagsRequest extends ApiFormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'tags' => ['required', 'array', 'min:1', 'max:500'],
            'tags.*' => ['required', 'string', 'max:40'],
        ];
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        /** @var list<string> */
        return $this->validated('tags');
    }
}
