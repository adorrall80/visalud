<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            foreach ($fieldRules as $rule) {
                $parameters = [];
                if (is_string($rule) && str_contains($rule, ':')) {
                    [$rule, $parameterList] = explode(':', $rule, 2);
                    $parameters = explode(',', $parameterList);
                }

                if ($rule !== 'required' && ($value === null || $value === '')) {
                    continue;
                }

                $valid = match ($rule) {
                    'required' => !($value === null || $value === '' || $value === []),
                    'string' => is_string($value),
                    'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                    'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
                    'date' => $this->validDate($value, 'Y-m-d'),
                    'notFuture' => is_string($value) && $value <= date('Y-m-d'),
                    'datetime' => $this->validDate($value, 'Y-m-d\TH:i') || $this->validDate($value, 'Y-m-d H:i:s'),
                    'in' => in_array((string) $value, $parameters, true),
                    'maxLength' => is_string($value) && mb_strlen($value) <= (int) ($parameters[0] ?? 0),
                    default => throw new \InvalidArgumentException("Regla no soportada: {$rule}"),
                };

                if (!$valid) {
                    $this->errors[$field][] = $this->message((string) $rule, $field, $parameters);
                    break;
                }
            }
        }
        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validateFile(?array $file, int $maxBytes, array $mimeTypes): bool
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return false;
        }
        if (($file['size'] ?? 0) > $maxBytes || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return false;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        return is_string($mime) && in_array($mime, $mimeTypes, true);
    }

    private function validDate(mixed $value, string $format): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
        return $date !== false && $date->format($format) === $value;
    }

    private function message(string $rule, string $field, array $parameters): string
    {
        return match ($rule) {
            'required' => "El campo {$field} es obligatorio.",
            'email' => "El campo {$field} debe ser un correo válido.",
            'date', 'datetime' => "El campo {$field} contiene una fecha inválida.",
            'notFuture' => "El campo {$field} no puede contener una fecha futura.",
            'integer' => "El campo {$field} debe ser un número entero.",
            'in' => "El valor seleccionado para {$field} no es válido.",
            'maxLength' => "El campo {$field} no puede superar {$parameters[0]} caracteres.",
            default => "El campo {$field} no es válido.",
        };
    }
}
