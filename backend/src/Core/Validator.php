<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Small rule-based validator inspired by Laravel's.
 *
 *   $data = Validator::validate($request->body, [
 *       'email'    => 'required|email|max:255',
 *       'quantity' => 'required|integer|min:1|max:10',
 *   ]);
 *
 * Throws HttpException(422) with per-field messages. Returns only the validated fields.
 * Supported rules: required, string, email, integer, min:N, max:N (length for strings, value for ints),
 * in:a,b,c, regex:/.../
 */
final class Validator
{
    /**
     * @param array<string, mixed>  $data
     * @param array<string, string|string[]> $rules  "a|b" string, or an array when a regex contains "|" 
     * @return array<string, mixed>
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        $clean = [];

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $ruleList = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $isInt = in_array('integer', $ruleList, true);

            if ($value === null || $value === '') {
                if (in_array('required', $ruleList, true)) {
                    $errors[$field][] = "The $field field is required.";
                }
                continue; // optional & missing: skip other rules
            }

            foreach ($ruleList as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = match ($name) {
                    'required' => null,
                    'string'   => is_string($value) ? null : "The $field must be a string.",
                    'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "The $field must be a valid email address.",
                    'integer'  => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "The $field must be an integer.",
                    'min'      => self::checkSize($field, $value, (int) $arg, $isInt, 'min'),
                    'max'      => self::checkSize($field, $value, (int) $arg, $isInt, 'max'),
                    'in'       => in_array((string) $value, explode(',', (string) $arg), true) ? null : "The selected $field is invalid.",
                    'regex'    => is_string($value) && preg_match((string) $arg, $value) ? null : "The $field format is invalid.",
                    default    => throw new \LogicException("Unknown validation rule: $name"),
                };
                if ($error !== null) {
                    $errors[$field][] = $error;
                    break; // one message per field is enough
                }
            }

            $clean[$field] = $isInt && !isset($errors[$field]) ? (int) $value : $value;
        }

        if ($errors) {
            throw HttpException::validation($errors);
        }
        return $clean;
    }

    private static function checkSize(string $field, mixed $value, int $limit, bool $isInt, string $kind): ?string
    {
        if ($isInt) {
            $n = (int) $value;
            if ($kind === 'min' && $n < $limit) return "The $field must be at least $limit.";
            if ($kind === 'max' && $n > $limit) return "The $field may not be greater than $limit.";
            return null;
        }
        $len = mb_strlen((string) $value);
        if ($kind === 'min' && $len < $limit) return "The $field must be at least $limit characters.";
        if ($kind === 'max' && $len > $limit) return "The $field may not be greater than $limit characters.";
        return null;
    }
}
