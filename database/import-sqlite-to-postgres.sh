#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'EOF'
Import Workout Memory data from a SQLite backup into a migrated PostgreSQL database.

Required:
  SOURCE_SQLITE=/path/to/database.sqlite
  DATABASE_URL=postgresql://user:password@host:5432/database?sslmode=require

Usage:
  SOURCE_SQLITE=/path/to/database.sqlite DATABASE_URL=postgresql://... ./database/import-sqlite-to-postgres.sh --yes

Options:
  --source PATH          SQLite backup path. Overrides SOURCE_SQLITE.
  --database-url URL     PostgreSQL target URL. Overrides DATABASE_URL / DB_URL.
  --yes                 Required safety confirmation.
  -h, --help            Show this help.
EOF
}

source_sqlite="${SOURCE_SQLITE:-}"
database_url="${DATABASE_URL:-${DB_URL:-}}"
confirmed="no"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --source)
            source_sqlite="${2:-}"
            shift 2
            ;;
        --source=*)
            source_sqlite="${1#*=}"
            shift
            ;;
        --database-url)
            database_url="${2:-}"
            shift 2
            ;;
        --database-url=*)
            database_url="${1#*=}"
            shift
            ;;
        --yes)
            confirmed="yes"
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

if [[ "$confirmed" != "yes" ]]; then
    echo "Refusing to import without --yes." >&2
    exit 2
fi

if [[ -z "$source_sqlite" || ! -f "$source_sqlite" ]]; then
    echo "SOURCE_SQLITE must point to a readable SQLite backup." >&2
    exit 2
fi

if [[ -z "$database_url" ]]; then
    echo "DATABASE_URL or DB_URL must contain the PostgreSQL target connection string." >&2
    exit 2
fi

for command in sqlite3 psql awk sed; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Missing required command: $command" >&2
        exit 2
    fi
done

integrity_check="$(sqlite3 "$source_sqlite" 'PRAGMA integrity_check;')"

if [[ "$integrity_check" != "ok" ]]; then
    echo "SQLite integrity check failed: $integrity_check" >&2
    exit 1
fi

tables=(
    users
    password_reset_tokens
    sessions
    cache
    cache_locks
    jobs
    job_batches
    failed_jobs
    exercises
    exercise_aliases
    user_profiles
    exercise_phrase_memories
    workout_sessions
    exercise_resolution_attempts
    workout_exercises
    workout_sets
    workout_change_events
    workout_shares
)

sequence_tables=(
    users
    jobs
    failed_jobs
    exercises
    exercise_aliases
    user_profiles
    exercise_phrase_memories
    workout_sessions
    exercise_resolution_attempts
    workout_exercises
    workout_sets
    workout_change_events
    workout_shares
)

truncate_tables=(
    workout_sets
    workout_exercises
    workout_change_events
    workout_shares
    exercise_resolution_attempts
    exercise_phrase_memories
    exercise_aliases
    workout_sessions
    user_profiles
    exercises
    sessions
    password_reset_tokens
    cache_locks
    cache
    jobs
    job_batches
    failed_jobs
)

quote_identifier() {
    local identifier="$1"
    printf '"%s"' "${identifier//\"/\"\"}"
}

column_names() {
    local table="$1"
    sqlite3 "$source_sqlite" "PRAGMA table_info($(quote_identifier "$table"));" | awk -F'|' '{print $2}'
}

quoted_column_csv() {
    local table="$1"
    local output=""
    local column

    while IFS= read -r column; do
        if [[ -n "$output" ]]; then
            output+=", "
        fi

        output+="$(quote_identifier "$column")"
    done < <(column_names "$table")

    printf '%s' "$output"
}

select_column_csv() {
    quoted_column_csv "$1"
}

order_clause() {
    local table="$1"

    if sqlite3 "$source_sqlite" "PRAGMA table_info($(quote_identifier "$table"));" | awk -F'|' '$6 == 1 && $2 == "id" { found = 1 } END { exit found ? 0 : 1 }'; then
        printf ' ORDER BY "id"'
    fi
}

tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT

echo "Checking target database..."
psql "$database_url" -v ON_ERROR_STOP=1 -qAtc 'select count(*) from migrations;' >/dev/null

echo "Exporting SQLite tables..."

for table in "${tables[@]}"; do
    csv_path="$tmp_dir/$table.csv"
    columns="$(select_column_csv "$table")"
    order_by="$(order_clause "$table")"

    sqlite3 "$source_sqlite" <<SQL
.mode csv
.nullvalue ''
.once '$csv_path'
SELECT $columns FROM $(quote_identifier "$table")$order_by;
SQL
done

echo "Truncating target app tables..."

truncate_sql='TRUNCATE TABLE '
for table in "${truncate_tables[@]}"; do
    if [[ "$truncate_sql" != 'TRUNCATE TABLE ' ]]; then
        truncate_sql+=', '
    fi

    truncate_sql+="$(quote_identifier "$table")"
done
truncate_sql+=' RESTART IDENTITY CASCADE;'

psql "$database_url" -v ON_ERROR_STOP=1 -qAtc "$truncate_sql" >/dev/null

echo "Importing rows into PostgreSQL..."

for table in "${tables[@]}"; do
    csv_path="$tmp_dir/$table.csv"
    columns="$(quoted_column_csv "$table")"

    psql "$database_url" -v ON_ERROR_STOP=1 -q <<SQL
\\copy $(quote_identifier "$table") ($columns) FROM '$csv_path' WITH (FORMAT csv, NULL '');
SQL
done

echo "Resetting sequences..."

for table in "${sequence_tables[@]}"; do
    psql "$database_url" -v ON_ERROR_STOP=1 -qAtc \
        "SELECT setval(pg_get_serial_sequence('$table', 'id'), COALESCE((SELECT MAX(id) FROM $(quote_identifier "$table")), 1), (SELECT COUNT(*) > 0 FROM $(quote_identifier "$table")));" >/dev/null
done

echo "Verifying row counts..."

for table in "${tables[@]}"; do
    source_count="$(sqlite3 "$source_sqlite" "SELECT COUNT(*) FROM $(quote_identifier "$table");")"
    target_count="$(psql "$database_url" -v ON_ERROR_STOP=1 -qAtc "SELECT COUNT(*) FROM $(quote_identifier "$table");")"

    if [[ "$source_count" != "$target_count" ]]; then
        echo "Count mismatch for $table: source=$source_count target=$target_count" >&2
        exit 1
    fi

    printf '  %-36s %s\n' "$table" "$target_count"
done

echo "Import complete."
