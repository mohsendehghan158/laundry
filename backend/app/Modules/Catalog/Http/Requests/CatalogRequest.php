<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Base for catalog create and update requests: the same rules serve both,
 * with fields required on create (POST) and optional on update (PUT).
 */
abstract class CatalogRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->actor()->can('catalog.manage');
    }

    /** "required" when creating, "sometimes" when updating. */
    protected function presence(): string
    {
        return $this->isMethod('POST') ? 'required' : 'sometimes';
    }

    /**
     * A record of this organization that has not been deleted.
     */
    protected function existsInOrganization(string $table): Exists
    {
        return Rule::exists($table, 'id')
            ->where('organization_id', $this->actor()->organization_id)
            ->withoutTrashed();
    }

    /**
     * Unique among this organization's live records, ignoring the record
     * being updated.
     */
    protected function uniqueInOrganization(string $table, string $column, ?int $ignore): Unique
    {
        return Rule::unique($table, $column)
            ->where('organization_id', $this->actor()->organization_id)
            ->withoutTrashed()
            ->ignore($ignore);
    }

    /**
     * Rules every sortable catalog record shares.
     *
     * @return array<string, list<mixed>>
     */
    protected function orderingRules(): array
    {
        return [
            'sort_order' => ['integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function routeId(string $parameter): ?int
    {
        $model = $this->route($parameter);

        return is_object($model) && method_exists($model, 'getKey') ? (int) $model->getKey() : null;
    }
}
