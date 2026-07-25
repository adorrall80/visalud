<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class DocumentoService
{
    private readonly PDO $database;
    private readonly string $storagePath;
    private readonly int $maxBytes;
    private readonly bool $compressImages;
    private readonly int $targetImageBytes;
    private readonly int $maxImageSide;

    private const MIME_EXTENSIONS = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
    ];

    public function __construct(Database $database)
    {
        $this->database = $database->connection();
        $config = require dirname(__DIR__, 2) . '/config/filesystems.php';
        $this->storagePath = rtrim((string) $config['documents'], '/\\');
        $this->maxBytes = max(1, (int) $config['max_upload_mb']) * 1024 * 1024;
        $this->compressImages = (bool) ($config['image_compression_enabled'] ?? true);
        $this->targetImageBytes = max(10, (int) ($config['image_target_kb'] ?? 50)) * 1024;
        $this->maxImageSide = max(320, (int) ($config['image_max_side'] ?? 1400));
    }

    public function allForFamily(int $familyId, array $filters = [], int $limit = 0, ?int $viewerId = null): array
    {
        $where = ['p.familia_id = :familia_id'];
        $parameters = ['familia_id' => $familyId];
        $deletePermissionSql = '';
        if ($viewerId !== null) {
            $parameters['viewer_uploader_id'] = $viewerId;
            $parameters['viewer_admin_id'] = $viewerId;
            $parameters['viewer_family_id'] = $familyId;
            $deletePermissionSql = ",
                    CASE WHEN d.subido_por_usuario_id = :viewer_uploader_id OR EXISTS (
                        SELECT 1 FROM familia_usuarios fu
                        INNER JOIN tipos rol ON rol.id = fu.tipo_rol_id
                        INNER JOIN procesos proceso ON proceso.id = rol.proceso_id
                        WHERE fu.familia_id = :viewer_family_id
                          AND fu.usuario_id = :viewer_admin_id
                          AND proceso.codigo = 'MIEMBRO_FAMILIA'
                          AND rol.codigo = 'ADMINISTRADOR'
                    ) THEN 1 ELSE 0 END AS puede_eliminar";
        }
        foreach (['persona_id' => 'd.persona_id', 'tipo_id' => 'd.tipo_id', 'atencion_id' => 'd.atencion_id'] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $where[] = "{$column} = :{$filter}";
                $parameters[$filter] = (int) $filters[$filter];
            }
        }
        $limitSql = $limit > 0 ? ' LIMIT ' . (int) $limit : '';
        $statement = $this->database->prepare(
            'SELECT d.*, p.nombre AS persona_nombre, t.nombre AS tipo_nombre, t.codigo AS tipo_codigo,
                    u.nombre AS subido_por_nombre' . $deletePermissionSql . '
             FROM documentos d
             INNER JOIN personas p ON p.id = d.persona_id
             INNER JOIN tipos t ON t.id = d.tipo_id
             INNER JOIN usuarios u ON u.id = d.subido_por_usuario_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY COALESCE(d.fecha_documento, DATE(d.created_at)) DESC, d.id DESC' . $limitSql
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function findForFamily(int $id, int $familyId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT d.*, p.nombre AS persona_nombre, t.nombre AS tipo_nombre, t.codigo AS tipo_codigo,
                    u.nombre AS subido_por_nombre
             FROM documentos d
             INNER JOIN personas p ON p.id = d.persona_id
             INNER JOIN tipos t ON t.id = d.tipo_id
             INNER JOIN usuarios u ON u.id = d.subido_por_usuario_id
             WHERE d.id = :id AND p.familia_id = :familia_id'
        );
        $statement->execute(['id' => $id, 'familia_id' => $familyId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(int $familyId, int $userId, array $data, ?array $file): int
    {
        $this->validateRelations($familyId, $data);
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Debes seleccionar un archivo.');
        }
        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new \InvalidArgumentException('El archivo recibido no es una carga vÃ¡lida.');
        }
        $inspected = $this->analyzeFile(
            (string) $file['tmp_name'],
            (string) ($file['name'] ?? ''),
            (int) ($file['size'] ?? 0),
        );
        $prepared = $this->prepareForStorage((string) $file['tmp_name'], $inspected);
        $relativePath = date('Y/m') . '/' . bin2hex(random_bytes(20)) . '.' . $prepared['extension'];
        $absolutePath = $this->storagePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $directory = dirname($absolutePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            $this->cleanupPreparedFile($prepared);
            throw new \RuntimeException('No fue posible preparar el almacenamiento de documentos.');
        }
        if (!$this->storePreparedFile($prepared, $absolutePath)) {
            $this->cleanupPreparedFile($prepared);
            throw new \RuntimeException('No fue posible guardar el documento.');
        }
        $this->cleanupPreparedFile($prepared);

        try {
            $statement = $this->database->prepare(
                'INSERT INTO documentos
                 (persona_id, atencion_id, tipo_id, subido_por_usuario_id,
                  nombre, archivo_ruta, mime_type, fecha_documento, descripcion)
                 VALUES (:persona_id, :atencion_id, :tipo_id, :usuario_id,
                         :nombre, :archivo_ruta, :mime_type, :fecha_documento, :descripcion)'
            );
            $statement->execute([
                'persona_id' => (int) $data['persona_id'],
                'atencion_id' => empty($data['atencion_id']) ? null : (int) $data['atencion_id'],
                'tipo_id' => (int) $data['tipo_id'],
                'usuario_id' => $userId,
                'nombre' => $this->documentName($data['nombre'] ?? '', (string) $file['name']),
                'archivo_ruta' => $relativePath,
                'mime_type' => $prepared['mime_type'],
                'fecha_documento' => $this->nullable($data['fecha_documento'] ?? null),
                'descripcion' => $this->nullable($data['descripcion'] ?? null),
            ]);
            return (int) $this->database->lastInsertId();
        } catch (\Throwable $exception) {
            @unlink($absolutePath);
            throw $exception;
        }
    }

    public function update(int $id, int $familyId, array $data, ?array $file): bool
    {
        $document = $this->findForFamily($id, $familyId);
        if ($document === null) {
            return false;
        }
        $this->validateRelations($familyId, $data);

        $replacement = null;
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('No fue posible recibir el archivo nuevo.');
            }
            if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
                throw new \InvalidArgumentException('El archivo recibido no es una carga válida.');
            }
            $inspected = $this->analyzeFile(
                (string) $file['tmp_name'],
                (string) ($file['name'] ?? ''),
                (int) ($file['size'] ?? 0),
            );
            $prepared = $this->prepareForStorage((string) $file['tmp_name'], $inspected);
            $relativePath = date('Y/m') . '/' . bin2hex(random_bytes(20)) . '.' . $prepared['extension'];
            $absolutePath = $this->absolutePath($relativePath);
            $directory = dirname($absolutePath);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                $this->cleanupPreparedFile($prepared);
                throw new \RuntimeException('No fue posible preparar el almacenamiento de documentos.');
            }
            if (!$this->storePreparedFile($prepared, $absolutePath)) {
                $this->cleanupPreparedFile($prepared);
                throw new \RuntimeException('No fue posible guardar el documento.');
            }
            $this->cleanupPreparedFile($prepared);
            $replacement = [
                'relative_path' => $relativePath,
                'absolute_path' => $absolutePath,
                'mime_type' => $prepared['mime_type'],
                'original_name' => (string) ($file['name'] ?? ''),
            ];
        }

        try {
            $statement = $this->database->prepare(
                'UPDATE documentos
                 SET atencion_id = :atencion_id, tipo_id = :tipo_id, nombre = :nombre,
                     archivo_ruta = :archivo_ruta, mime_type = :mime_type,
                     fecha_documento = :fecha_documento, descripcion = :descripcion
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'atencion_id' => empty($data['atencion_id']) ? null : (int) $data['atencion_id'],
                'tipo_id' => (int) $data['tipo_id'],
                'nombre' => $this->documentName($data['nombre'] ?? '', $replacement['original_name'] ?? (string) $document['nombre']),
                'archivo_ruta' => $replacement['relative_path'] ?? (string) $document['archivo_ruta'],
                'mime_type' => $replacement['mime_type'] ?? (string) $document['mime_type'],
                'fecha_documento' => $this->nullable($data['fecha_documento'] ?? null),
                'descripcion' => $this->nullable($data['descripcion'] ?? null),
            ]);
            if ($replacement !== null) {
                $oldPath = $this->absolutePath((string) $document['archivo_ruta']);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            return true;
        } catch (\Throwable $exception) {
            if ($replacement !== null && is_file($replacement['absolute_path'])) {
                @unlink($replacement['absolute_path']);
            }
            throw $exception;
        }
    }

    public function downloadForFamily(int $id, int $familyId): ?array
    {
        $document = $this->findForFamily($id, $familyId);
        if ($document === null) {
            return null;
        }
        $document['absolute_path'] = $this->absolutePath((string) $document['archivo_ruta']);
        $document['download_name'] = $this->downloadName(
            (string) $document['nombre'],
            (string) $document['archivo_ruta'],
        );
        return $document;
    }

    public function delete(int $id, int $familyId, int $userId): bool
    {
        $document = $this->findForFamily($id, $familyId);
        if ($document === null) {
            return false;
        }
        if (!$this->canDelete($document, $familyId, $userId)) {
            throw new \RuntimeException('Solo quien subió el documento o un administrador puede eliminarlo.');
        }
        $statement = $this->database->prepare(
            'DELETE d FROM documentos d INNER JOIN personas p ON p.id = d.persona_id
             WHERE d.id = :id AND p.familia_id = :familia_id'
        );
        $statement->execute(['id' => $id, 'familia_id' => $familyId]);
        if ($statement->rowCount() === 1) {
            $path = $this->absolutePath((string) $document['archivo_ruta']);
            if (is_file($path)) {
                @unlink($path);
            }
            return true;
        }
        return false;
    }

    public function canDelete(array $document, int $familyId, int $userId): bool
    {
        if ((int) $document['subido_por_usuario_id'] === $userId) {
            return true;
        }
        $statement = $this->database->prepare(
            "SELECT 1
             FROM familia_usuarios fu
             INNER JOIN tipos rol ON rol.id = fu.tipo_rol_id
             INNER JOIN procesos proceso ON proceso.id = rol.proceso_id
             WHERE fu.familia_id = :familia_id
               AND fu.usuario_id = :usuario_id
               AND proceso.codigo = 'MIEMBRO_FAMILIA'
               AND rol.codigo = 'ADMINISTRADOR'
             LIMIT 1"
        );
        $statement->execute(['familia_id' => $familyId, 'usuario_id' => $userId]);
        return $statement->fetchColumn() !== false;
    }

    public function types(): array
    {
        return $this->database->query(
            "SELECT t.id, t.codigo, t.nombre FROM tipos t
             INNER JOIN procesos p ON p.id = t.proceso_id
             WHERE p.codigo = 'DOCUMENTO' AND t.activo = 1 ORDER BY t.nombre"
        )->fetchAll();
    }

    public function analyzeFile(string $path, string $originalName, int $size): array
    {
        if (!is_file($path) || $size <= 0 || $size > $this->maxBytes) {
            throw new \InvalidArgumentException('El archivo estÃ¡ vacÃ­o o supera el tamaÃ±o permitido.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])) {
            throw new \InvalidArgumentException('Solo se permiten archivos PDF, JPG, PNG o WEBP.');
        }
        $safeOriginal = basename(str_replace('\\', '/', $originalName));
        $extension = strtolower((string) pathinfo($safeOriginal, PATHINFO_EXTENSION));
        if (!in_array($extension, self::MIME_EXTENSIONS[$mime], true)) {
            throw new \InvalidArgumentException('La extensiÃ³n del archivo no coincide con su contenido.');
        }
        return ['mime_type' => $mime, 'extension' => $extension];
    }

    private function prepareForStorage(string $sourcePath, array $inspected): array
    {
        $prepared = [
            'path' => $sourcePath,
            'mime_type' => (string) $inspected['mime_type'],
            'extension' => (string) $inspected['extension'],
            'temporary' => false,
        ];
        if (!$this->compressImages || !str_starts_with($prepared['mime_type'], 'image/')) {
            return $prepared;
        }

        $compressed = $this->compressImage($sourcePath);
        if ($compressed === null) {
            return $prepared;
        }

        return [
            'path' => $compressed,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'temporary' => true,
        ];
    }

    private function compressImage(string $sourcePath): ?string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return null;
        }
        $content = file_get_contents($sourcePath);
        if ($content === false) {
            return null;
        }
        $source = @imagecreatefromstring($content);
        if (!$source instanceof \GdImage) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $baseScale = min(1.0, $this->maxImageSide / max($width, $height));
        $qualities = [82, 72, 62, 52, 42, 35];
        $scale = $baseScale;
        $bestPath = null;

        while ($scale >= 0.35) {
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            if (!$canvas instanceof \GdImage) {
                break;
            }
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            foreach ($qualities as $quality) {
                $path = tempnam(sys_get_temp_dir(), 'visalud-img-');
                if (!is_string($path)) {
                    continue;
                }
                imagejpeg($canvas, $path, $quality);
                $size = filesize($path);
                if (is_int($size)) {
                    if ($bestPath === null || $size < (int) filesize($bestPath)) {
                        if ($bestPath !== null && is_file($bestPath)) {
                            @unlink($bestPath);
                        }
                        $bestPath = $path;
                    } else {
                        @unlink($path);
                    }
                    if ($size <= $this->targetImageBytes) {
                        imagedestroy($canvas);
                        imagedestroy($source);
                        return $bestPath;
                    }
                } else {
                    @unlink($path);
                }
            }
            imagedestroy($canvas);
            $scale *= 0.82;
        }

        imagedestroy($source);
        return $bestPath;
    }

    private function storePreparedFile(array $prepared, string $absolutePath): bool
    {
        if ((bool) $prepared['temporary']) {
            return rename((string) $prepared['path'], $absolutePath);
        }
        return move_uploaded_file((string) $prepared['path'], $absolutePath);
    }

    private function cleanupPreparedFile(array $prepared): void
    {
        if ((bool) $prepared['temporary'] && is_file((string) $prepared['path'])) {
            @unlink((string) $prepared['path']);
        }
    }

    private function validateRelations(int $familyId, array $data): void
    {
        $person = $this->database->prepare('SELECT id FROM personas WHERE id = :id AND familia_id = :familia_id');
        $person->execute(['id' => $data['persona_id'], 'familia_id' => $familyId]);
        if ($person->fetchColumn() === false) {
            throw new \InvalidArgumentException('La persona seleccionada no pertenece a la familia.');
        }
        $type = $this->database->prepare(
            "SELECT t.id FROM tipos t INNER JOIN procesos p ON p.id = t.proceso_id
             WHERE t.id = :id AND p.codigo = 'DOCUMENTO' AND t.activo = 1"
        );
        $type->execute(['id' => $data['tipo_id']]);
        if ($type->fetchColumn() === false) {
            throw new \InvalidArgumentException('El tipo seleccionado no corresponde a un documento.');
        }
        $this->validateOptionalRelation('atenciones', $data['atencion_id'] ?? null, (int) $data['persona_id'], $familyId);
    }

    private function validateOptionalRelation(string $table, mixed $id, int $personId, int $familyId): void
    {
        if (empty($id)) {
            return;
        }
        $statement = $this->database->prepare(
            "SELECT r.id FROM {$table} r INNER JOIN personas p ON p.id = r.persona_id
             WHERE r.id = :id AND r.persona_id = :persona_id AND p.familia_id = :familia_id"
        );
        $statement->execute(['id' => $id, 'persona_id' => $personId, 'familia_id' => $familyId]);
        if ($statement->fetchColumn() === false) {
            throw new \InvalidArgumentException('El registro asociado no corresponde a la persona seleccionada.');
        }
    }

    private function absolutePath(string $relativePath): string
    {
        $normalized = str_replace('\\', '/', $relativePath);
        if ($normalized === '' || str_starts_with($normalized, '/') || str_contains($normalized, '../')) {
            throw new \RuntimeException('Ruta de documento invÃ¡lida.');
        }
        return $this->storagePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    }

    private function documentName(mixed $provided, string $original): string
    {
        $name = trim((string) $provided);
        if ($name === '') {
            $name = basename(str_replace('\\', '/', $original));
        }
        return mb_substr($name, 0, 255);
    }

    private function downloadName(string $name, string $relativePath): string
    {
        $name = basename(str_replace('\\', '/', trim($name)));
        $extension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));
        if ($extension === '') {
            return $name;
        }
        if (strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) === $extension) {
            return $name;
        }
        $maximumNameLength = 255 - mb_strlen($extension) - 1;
        return mb_substr($name, 0, max(1, $maximumNameLength)) . '.' . $extension;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
