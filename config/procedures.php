<?php
require_once __DIR__ . '/config.php';

function clinic_current_user_id(): ?int
{
    $userId = (int) ($_SESSION['user_no'] ?? $_SESSION['User_ID'] ?? $_SESSION['user_id'] ?? 0);

    return $userId > 0 ? $userId : null;
}

function clinic_sp_assert_name(string $name): void
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
        throw new InvalidArgumentException('Invalid stored procedure name.');
    }
}

function clinic_sp_param_types(array $params): string
{
    $types = '';
    foreach ($params as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }

    return $types;
}

function clinic_sp_bind(mysqli_stmt $stmt, array $params, ?string $types = null): void
{
    if ($params === []) {
        return;
    }

    $types = $types ?: clinic_sp_param_types($params);
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = $value;
    }

    $bind = [$types];
    foreach ($refs as $key => $_) {
        $bind[] = &$refs[$key];
    }

    $stmt->bind_param(...$bind);
}

function clinic_sp_flush(mysqli $conn): void
{
    while ($conn->more_results()) {
        $conn->next_result();
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
}

function clinic_sp_rows(string $procedure, array $params = [], ?string $types = null): array
{
    global $conn;

    clinic_sp_assert_name($procedure);
    $placeholders = $params === [] ? '' : implode(',', array_fill(0, count($params), '?'));
    $stmt = $conn->prepare('CALL `' . $procedure . '`(' . $placeholders . ')');
    clinic_sp_bind($stmt, $params, $types);
    $stmt->execute();

    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    clinic_sp_flush($conn);

    return $rows;
}

function clinic_sp_one(string $procedure, array $params = [], ?string $types = null): ?array
{
    $rows = clinic_sp_rows($procedure, $params, $types);

    return $rows[0] ?? null;
}

function clinic_sp_exec(string $procedure, array $params = [], ?string $types = null): void
{
    global $conn;

    clinic_sp_assert_name($procedure);
    $placeholders = $params === [] ? '' : implode(',', array_fill(0, count($params), '?'));
    $stmt = $conn->prepare('CALL `' . $procedure . '`(' . $placeholders . ')');
    clinic_sp_bind($stmt, $params, $types);
    $stmt->execute();
    $stmt->close();
    clinic_sp_flush($conn);
}
