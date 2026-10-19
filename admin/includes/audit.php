<?php

function logAdminAction(
    PDO $pdo,
    string $action,
    string $entityType,
    ?int $entityId = null,
    ?string $description = null,
    ?array $oldValues = null,
    ?array $newValues = null
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_audit_logs
            (
                admin_user_id,
                action,
                entity_type,
                entity_id,
                description,
                old_values,
                new_values,
                ip_address
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
            $action,
            $entityType,
            $entityId,
            $description,
            $oldValues !== null
                ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
            $newValues !== null
                ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
            substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null
        ]);
    } catch (Throwable $e) {
        error_log('ADMIN AUDIT LOG ERROR: ' . $e->getMessage());
    }
}
