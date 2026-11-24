<?php

declare(strict_types=1);

namespace App\Support\Http\Requests;

use App\Modules\Identity\Models\User;
use App\Support\Text\Digits;
use Illuminate\Foundation\Http\FormRequest;

abstract class ApiFormRequest extends FormRequest
{
    /**
     * Input keys whose digits are normalised to Latin before validation,
     * so values typed on a Persian keyboard are accepted.
     *
     * @var list<string>
     */
    protected array $latinDigits = [];

    /**
     * Input keys that are lower-cased before validation.
     *
     * @var list<string>
     */
    protected array $lowercase = [];

    /**
     * Input keys that are upper-cased before validation.
     *
     * @var list<string>
     */
    protected array $uppercase = [];

    public function authorize(): bool
    {
        return true;
    }

    public function actor(): User
    {
        /** @var User */
        return $this->user();
    }

    protected function prepareForValidation(): void
    {
        $this->transformInput($this->latinDigits, Digits::toLatin(...));
        $this->transformInput($this->lowercase, mb_strtolower(...));
        $this->transformInput($this->uppercase, mb_strtoupper(...));
    }

    /**
     * @param  list<string>  $keys
     * @param  callable(string): string  $transform
     */
    private function transformInput(array $keys, callable $transform): void
    {
        foreach ($keys as $key) {
            $value = $this->input($key);

            if (is_string($value)) {
                $this->merge([$key => $transform(trim($value))]);
            }
        }
    }
}
