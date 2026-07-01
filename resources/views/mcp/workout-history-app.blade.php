<x-mcp::app :title="$title">
    <x-slot:head>
        <style>
            :root {
                color-scheme: light dark;
                --wm-bg: var(--color-background-primary, light-dark(#f7f8f5, #151715));
                --wm-surface: var(--color-background-secondary, light-dark(#ffffff, #20231f));
                --wm-surface-muted: var(--color-background-tertiary, light-dark(#eef1ea, #2a2e29));
                --wm-text: var(--color-text-primary, light-dark(#1c211b, #f5f7f0));
                --wm-muted: var(--color-text-secondary, light-dark(#62695e, #aab2a3));
                --wm-border: var(--color-border-primary, light-dark(#dfe4d9, #3d443a));
                --wm-accent: light-dark(#256f46, #78c78f);
                --wm-accent-strong: light-dark(#174c31, #a9e5b4);
                --wm-warm: light-dark(#a45f19, #f0b35b);
                --wm-danger: light-dark(#9f2d2d, #f39191);
                --wm-radius: 8px;
                --wm-font: var(--font-sans, "Aptos", "Segoe UI", ui-sans-serif, system-ui, sans-serif);
                --wm-mono: var(--font-mono, "SFMono-Regular", "Cascadia Mono", ui-monospace, monospace);
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                background: var(--wm-bg);
                color: var(--wm-text);
                font-family: var(--wm-font);
                font-size: 14px;
                line-height: 1.4;
            }

            button {
                font: inherit;
            }

            .history-shell {
                min-height: 520px;
                padding: 18px;
            }

            .history-header {
                align-items: end;
                border-bottom: 1px solid var(--wm-border);
                display: flex;
                gap: 16px;
                justify-content: space-between;
                padding-bottom: 14px;
            }

            .eyebrow {
                color: var(--wm-accent);
                font-size: 11px;
                font-weight: 700;
                letter-spacing: .08em;
                margin: 0 0 4px;
                text-transform: uppercase;
            }

            h1,
            h2,
            h3,
            p {
                margin-top: 0;
            }

            h1 {
                font-size: 22px;
                line-height: 1.1;
                margin-bottom: 0;
            }

            .pager {
                align-items: center;
                display: flex;
                gap: 8px;
            }

            .page-count {
                color: var(--wm-muted);
                font-family: var(--wm-mono);
                font-size: 12px;
                min-width: 64px;
                text-align: center;
            }

            .icon-button {
                align-items: center;
                background: var(--wm-surface);
                border: 1px solid var(--wm-border);
                border-radius: var(--wm-radius);
                color: var(--wm-text);
                cursor: pointer;
                display: inline-flex;
                font-size: 18px;
                height: 34px;
                justify-content: center;
                line-height: 1;
                transition: background-color .15s ease, border-color .15s ease, color .15s ease;
                width: 38px;
            }

            .icon-button:hover:not(:disabled),
            .icon-button:focus-visible {
                border-color: var(--wm-accent);
                color: var(--wm-accent-strong);
                outline: none;
            }

            .icon-button:disabled {
                color: var(--wm-muted);
                cursor: not-allowed;
                opacity: .45;
            }

            .history-grid {
                display: grid;
                gap: 16px;
                grid-template-columns: minmax(260px, 360px) minmax(0, 1fr);
                padding-top: 16px;
            }

            .session-column,
            .detail-column {
                min-width: 0;
            }

            .column-title {
                align-items: center;
                color: var(--wm-muted);
                display: flex;
                font-size: 12px;
                font-weight: 700;
                justify-content: space-between;
                letter-spacing: .06em;
                margin-bottom: 10px;
                text-transform: uppercase;
            }

            .status-line {
                color: var(--wm-muted);
                font-size: 13px;
                margin-bottom: 10px;
                min-height: 20px;
            }

            .status-line.error {
                color: var(--wm-danger);
            }

            .session-list {
                display: grid;
                gap: 8px;
            }

            .session-button {
                background: var(--wm-surface);
                border: 1px solid var(--wm-border);
                border-radius: var(--wm-radius);
                color: var(--wm-text);
                cursor: pointer;
                display: grid;
                gap: 7px;
                padding: 12px;
                text-align: left;
                transition: background-color .15s ease, border-color .15s ease, transform .15s ease;
                width: 100%;
            }

            .session-button:hover,
            .session-button:focus-visible {
                border-color: var(--wm-accent);
                outline: none;
                transform: translateY(-1px);
            }

            .session-button.active {
                background: color-mix(in srgb, var(--wm-accent) 11%, var(--wm-surface));
                border-color: var(--wm-accent);
            }

            .session-topline,
            .session-meta,
            .metric-row {
                align-items: center;
                display: flex;
                gap: 8px;
                min-width: 0;
            }

            .session-topline {
                justify-content: space-between;
            }

            .session-name {
                font-size: 15px;
                font-weight: 700;
                overflow-wrap: anywhere;
            }

            .session-date {
                color: var(--wm-muted);
                flex: 0 0 auto;
                font-family: var(--wm-mono);
                font-size: 11px;
            }

            .pill {
                background: var(--wm-surface-muted);
                border: 1px solid transparent;
                border-radius: 999px;
                color: var(--wm-muted);
                flex: 0 0 auto;
                font-size: 11px;
                font-weight: 700;
                padding: 2px 7px;
                text-transform: uppercase;
            }

            .exercise-line {
                color: var(--wm-muted);
                font-size: 13px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .metric-row {
                color: var(--wm-muted);
                flex-wrap: wrap;
                font-family: var(--wm-mono);
                font-size: 11px;
            }

            .detail-shell {
                background: var(--wm-surface);
                border: 1px solid var(--wm-border);
                border-radius: var(--wm-radius);
                min-height: 424px;
                padding: 16px;
            }

            .empty-state {
                align-items: center;
                color: var(--wm-muted);
                display: flex;
                min-height: 320px;
                justify-content: center;
                text-align: center;
            }

            .detail-header {
                border-bottom: 1px solid var(--wm-border);
                display: grid;
                gap: 8px;
                padding-bottom: 14px;
            }

            .detail-title-row {
                align-items: start;
                display: flex;
                gap: 14px;
                justify-content: space-between;
            }

            .detail-title-row h2 {
                font-size: 21px;
                line-height: 1.16;
                margin-bottom: 0;
                overflow-wrap: anywhere;
            }

            .detail-date {
                color: var(--wm-muted);
                font-family: var(--wm-mono);
                font-size: 12px;
                white-space: nowrap;
            }

            .detail-meta {
                color: var(--wm-muted);
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                font-size: 13px;
            }

            .notes {
                background: var(--wm-surface-muted);
                border-radius: var(--wm-radius);
                color: var(--wm-text);
                margin-top: 12px;
                padding: 10px 12px;
                white-space: pre-wrap;
            }

            .exercise-stack {
                display: grid;
                gap: 14px;
                padding-top: 16px;
            }

            .exercise-block {
                border-bottom: 1px solid var(--wm-border);
                padding-bottom: 14px;
            }

            .exercise-block:last-child {
                border-bottom: 0;
                padding-bottom: 0;
            }

            .exercise-block h3 {
                align-items: center;
                display: flex;
                font-size: 15px;
                justify-content: space-between;
                margin-bottom: 8px;
            }

            .set-list {
                display: grid;
                gap: 5px;
            }

            .set-row {
                align-items: center;
                background: var(--wm-surface-muted);
                border-radius: 6px;
                color: var(--wm-muted);
                display: flex;
                flex-wrap: wrap;
                gap: 7px;
                padding: 7px 9px;
            }

            .set-number {
                color: var(--wm-accent-strong);
                font-family: var(--wm-mono);
                font-size: 11px;
                font-weight: 700;
            }

            .small-muted {
                color: var(--wm-muted);
                font-size: 12px;
            }

            @media (max-width: 760px) {
                .history-shell {
                    padding: 14px;
                }

                .history-header {
                    align-items: stretch;
                    flex-direction: column;
                }

                .pager {
                    justify-content: space-between;
                }

                .history-grid {
                    grid-template-columns: 1fr;
                }

                .detail-shell {
                    min-height: 280px;
                }

                .detail-title-row {
                    display: grid;
                }

                .detail-date {
                    white-space: normal;
                }
            }
        </style>
        <script type="module">
            const defaultQuery = {
                limit: 20,
                cursor: null,
                since: null,
                kind: null,
                selected_workout_id: null,
            };

            const state = {
                app: null,
                query: { ...defaultQuery },
                sessions: [],
                pagination: {
                    limit: 20,
                    next_cursor: null,
                    previous_cursor: null,
                    has_next_page: false,
                    has_previous_page: false,
                },
                selectedId: null,
                pageIndex: 1,
            };

            const elements = {};

            function getElement(id) {
                return elements[id] ??= document.getElementById(id);
            }

            function scheduleResize() {
                window.requestAnimationFrame(() => state.app?.resize?.());
            }

            function parseToolData(result) {
                if (result?.structuredContent) {
                    return result.structuredContent;
                }

                const text = result?.content?.[0]?.text;

                if (!text) {
                    return {};
                }

                try {
                    return JSON.parse(text);
                } catch {
                    return {};
                }
            }

            function readArguments(params) {
                const payload = params?.arguments ?? params?.input ?? params ?? {};

                if (typeof payload === "string") {
                    try {
                        return JSON.parse(payload);
                    } catch {
                        return {};
                    }
                }

                return payload && typeof payload === "object" ? payload : {};
            }

            function applyInitialQueryFromToolResult(params) {
                const data = parseToolData(params?.result ?? params);

                if (data.initial_query) {
                    state.query = normalizeQuery(data.initial_query);
                }
            }

            function normalizeQuery(input) {
                const next = { ...state.query };

                if (Number.isInteger(input.limit)) {
                    next.limit = Math.min(50, Math.max(1, input.limit));
                }

                for (const key of ["cursor", "since", "kind"]) {
                    if (Object.prototype.hasOwnProperty.call(input, key)) {
                        next[key] = input[key] || null;
                    }
                }

                if (Object.prototype.hasOwnProperty.call(input, "selected_workout_id")) {
                    const value = Number(input.selected_workout_id);
                    next.selected_workout_id = Number.isInteger(value) ? value : null;
                }

                return next;
            }

            function formatDate(value) {
                if (!value) {
                    return "No date";
                }

                const date = new Date(value);

                if (Number.isNaN(date.getTime())) {
                    return "No date";
                }

                return new Intl.DateTimeFormat(undefined, {
                    day: "2-digit",
                    month: "short",
                    year: "numeric",
                }).format(date);
            }

            function compactDate(value) {
                if (!value) {
                    return "--";
                }

                const date = new Date(value);

                if (Number.isNaN(date.getTime())) {
                    return "--";
                }

                return new Intl.DateTimeFormat(undefined, {
                    day: "2-digit",
                    month: "short",
                }).format(date);
            }

            function setStatus(text, isError = false) {
                const status = getElement("history-status");
                status.textContent = text;
                status.classList.toggle("error", isError);
            }

            function updatePager() {
                getElement("previous-page").disabled = !state.pagination.previous_cursor;
                getElement("next-page").disabled = !state.pagination.next_cursor;
                getElement("page-count").textContent = `Page ${state.pageIndex}`;
            }

            function describeExercises(session) {
                const names = session.exercise_names ?? [];

                if (names.length === 0) {
                    return "No exercises recorded";
                }

                return names.slice(0, 4).join(", ") + (names.length > 4 ? ` +${names.length - 4}` : "");
            }

            function metricText(session) {
                const signals = session.top_level_volume_signals ?? {};
                const parts = [
                    `${session.set_count ?? 0} sets`,
                    `${signals.loaded_reps ?? 0} loaded reps`,
                    `${signals.bodyweight_reps ?? 0} bodyweight reps`,
                ];

                if ((signals.duration_seconds ?? 0) > 0) {
                    parts.push(signals.duration_display ?? `${signals.duration_seconds}s`);
                }

                if ((signals.distance_meters ?? 0) > 0) {
                    parts.push(`${signals.distance_meters} m`);
                }

                return parts.join(" / ");
            }

            function renderSessions() {
                const list = getElement("session-list");
                list.textContent = "";

                if (state.sessions.length === 0) {
                    const empty = document.createElement("div");
                    empty.className = "empty-state";
                    empty.textContent = "No sessions match this view.";
                    list.append(empty);
                    return;
                }

                for (const session of state.sessions) {
                    const button = document.createElement("button");
                    button.type = "button";
                    button.className = "session-button";
                    button.dataset.workoutId = session.id;
                    button.classList.toggle("active", session.id === state.selectedId);
                    button.addEventListener("click", () => selectWorkout(session.id));

                    const topline = document.createElement("div");
                    topline.className = "session-topline";

                    const name = document.createElement("div");
                    name.className = "session-name";
                    name.textContent = session.name ?? "Workout";

                    const date = document.createElement("div");
                    date.className = "session-date";
                    date.textContent = compactDate(session.started_at);

                    const meta = document.createElement("div");
                    meta.className = "session-meta";

                    const kind = document.createElement("span");
                    kind.className = "pill";
                    kind.textContent = session.kind ?? "mixed";

                    const exercises = document.createElement("div");
                    exercises.className = "exercise-line";
                    exercises.textContent = describeExercises(session);

                    const metrics = document.createElement("div");
                    metrics.className = "metric-row";
                    metrics.textContent = metricText(session);

                    topline.append(name, date);
                    meta.append(kind, exercises);
                    button.append(topline, meta, metrics);
                    list.append(button);
                }
            }

            function setDetailLoading(text) {
                const detail = getElement("detail-content");
                detail.innerHTML = "";
                const empty = document.createElement("div");
                empty.className = "empty-state";
                empty.textContent = text;
                detail.append(empty);
                scheduleResize();
            }

            function renderWorkout(workout) {
                const detail = getElement("detail-content");
                detail.innerHTML = "";

                if (!workout) {
                    setDetailLoading("Workout not found.");
                    return;
                }

                const header = document.createElement("div");
                header.className = "detail-header";

                const titleRow = document.createElement("div");
                titleRow.className = "detail-title-row";

                const title = document.createElement("h2");
                title.textContent = workout.name ?? "Workout";

                const date = document.createElement("div");
                date.className = "detail-date";
                date.textContent = formatDate(workout.started_at);

                const meta = document.createElement("div");
                meta.className = "detail-meta";
                meta.textContent = [
                    workout.kind ?? "mixed",
                    workout.status ?? "unknown",
                    `${workout.set_count ?? 0} sets`,
                    workout.perceived_effort ? `RPE ${workout.perceived_effort}` : null,
                    workout.bodyweight_kg ? `${workout.bodyweight_kg} kg bodyweight` : null,
                ].filter(Boolean).join(" / ");

                titleRow.append(title, date);
                header.append(titleRow, meta);

                if (workout.notes) {
                    const notes = document.createElement("div");
                    notes.className = "notes";
                    notes.textContent = workout.notes;
                    header.append(notes);
                }

                const stack = document.createElement("div");
                stack.className = "exercise-stack";

                for (const exercise of workout.exercises ?? []) {
                    stack.append(renderExercise(exercise));
                }

                if ((workout.exercises ?? []).length === 0) {
                    const empty = document.createElement("div");
                    empty.className = "small-muted";
                    empty.textContent = "No exercises recorded.";
                    stack.append(empty);
                }

                detail.append(header, stack);
                scheduleResize();
            }

            function renderExercise(exercise) {
                const block = document.createElement("section");
                block.className = "exercise-block";

                const heading = document.createElement("h3");
                const name = document.createElement("span");
                name.textContent = exercise.name ?? "Exercise";
                const count = document.createElement("span");
                count.className = "small-muted";
                count.textContent = `${(exercise.sets ?? []).length} sets`;
                heading.append(name, count);

                const list = document.createElement("div");
                list.className = "set-list";

                for (const set of exercise.sets ?? []) {
                    list.append(renderSet(set));
                }

                if ((exercise.sets ?? []).length === 0) {
                    const empty = document.createElement("div");
                    empty.className = "small-muted";
                    empty.textContent = "No sets recorded.";
                    list.append(empty);
                }

                if (exercise.notes) {
                    const notes = document.createElement("p");
                    notes.className = "small-muted";
                    notes.textContent = exercise.notes;
                    block.append(heading, notes, list);
                    return block;
                }

                block.append(heading, list);
                return block;
            }

            function renderSet(set) {
                const row = document.createElement("div");
                row.className = "set-row";

                const number = document.createElement("span");
                number.className = "set-number";
                number.textContent = `#${set.set_number ?? "?"}`;
                row.append(number);

                for (const part of setParts(set)) {
                    const span = document.createElement("span");
                    span.textContent = part;
                    row.append(span);
                }

                return row;
            }

            function setParts(set) {
                const parts = [];

                if (set.reps !== null && set.reps !== undefined) {
                    parts.push(`${set.reps} reps`);
                }

                if (set.load_kg !== null && set.load_kg !== undefined) {
                    parts.push(`${set.load_kg} kg`);
                }

                if (set.duration_display) {
                    parts.push(set.duration_display);
                }

                if (set.distance_meters !== null && set.distance_meters !== undefined) {
                    parts.push(`${set.distance_meters} m`);
                }

                if (set.rpe !== null && set.rpe !== undefined) {
                    parts.push(`RPE ${set.rpe}`);
                }

                if (set.side) {
                    parts.push(set.side);
                }

                if (set.is_warmup) {
                    parts.push("warmup");
                }

                if (parts.length === 0) {
                    parts.push(set.raw_set_text ?? "Logged set");
                }

                return parts;
            }

            async function loadSessions(cursor = null, preferredWorkoutId = null, direction = 0) {
                setStatus("Loading sessions...");
                const args = {
                    limit: state.query.limit || defaultQuery.limit,
                };

                if (cursor) {
                    args.cursor = cursor;
                }

                for (const key of ["since", "kind"]) {
                    if (state.query[key]) {
                        args[key] = state.query[key];
                    }
                }

                try {
                    const result = await state.app.callServerTool("list_recent_workouts", args);

                    if (result.isError) {
                        throw new Error(result.content?.[0]?.text ?? "Could not load sessions.");
                    }

                    const data = parseToolData(result);
                    state.sessions = data.sessions ?? [];
                    state.pagination = data.pagination ?? state.pagination;
                    state.query.cursor = cursor;
                    state.pageIndex = Math.max(1, state.pageIndex + direction);
                    state.selectedId = preferredWorkoutId;
                    renderSessions();
                    updatePager();
                    setStatus(`${state.sessions.length} sessions loaded`);

                    const selected = preferredWorkoutId
                        ? preferredWorkoutId
                        : state.sessions[0]?.id;

                    if (selected) {
                        await selectWorkout(selected);
                    } else {
                        setDetailLoading("No workout selected.");
                    }
                } catch (error) {
                    setStatus(error instanceof Error ? error.message : "Could not load sessions.", true);
                    state.sessions = [];
                    renderSessions();
                    updatePager();
                    setDetailLoading("No workout selected.");
                }
            }

            async function selectWorkout(workoutId) {
                state.selectedId = workoutId;
                renderSessions();
                setDetailLoading("Loading workout...");

                try {
                    const result = await state.app.callServerTool("get_workout", { workout_id: workoutId });

                    if (result.isError) {
                        throw new Error(result.content?.[0]?.text ?? "Could not load workout.");
                    }

                    const data = parseToolData(result);
                    renderWorkout(data.workout ?? null);

                    if (data.workout) {
                        await state.app.updateModelContext({
                            current_view: "workout_history",
                            selected_workout: {
                                id: data.workout.id,
                                name: data.workout.name,
                                started_at: data.workout.started_at,
                                exercise_names: (data.workout.exercises ?? []).map((exercise) => exercise.name),
                            },
                        }).catch(() => {});
                    }
                } catch (error) {
                    setStatus(error instanceof Error ? error.message : "Could not load workout.", true);
                    setDetailLoading("Workout could not be loaded.");
                }
            }

            createMcpApp(async (app) => {
                state.app = app;
                app.autoResize();
                app.onToolInput((params) => {
                    state.query = normalizeQuery(readArguments(params));
                });
                app.onToolResult(applyInitialQueryFromToolResult);

                getElement("previous-page").addEventListener("click", () => {
                    if (state.pagination.previous_cursor) {
                        loadSessions(state.pagination.previous_cursor, null, -1);
                    }
                });

                getElement("next-page").addEventListener("click", () => {
                    if (state.pagination.next_cursor) {
                        loadSessions(state.pagination.next_cursor, null, 1);
                    }
                });

                await new Promise((resolve) => setTimeout(resolve, 0));
                await loadSessions(state.query.cursor, state.query.selected_workout_id, 0);
            });
        </script>
    </x-slot:head>

    <div id="workout-history-root" class="history-shell">
        <header class="history-header">
            <div>
                <p class="eyebrow">Training log</p>
                <h1>Workout history</h1>
            </div>
            <nav class="pager" aria-label="Workout history pages">
                <button id="previous-page" type="button" class="icon-button" aria-label="Previous page" disabled>
                    <span aria-hidden="true">&larr;</span>
                </button>
                <span id="page-count" class="page-count">Page 1</span>
                <button id="next-page" type="button" class="icon-button" aria-label="Next page" disabled>
                    <span aria-hidden="true">&rarr;</span>
                </button>
            </nav>
        </header>

        <main class="history-grid">
            <section class="session-column" aria-label="Training sessions">
                <div class="column-title">
                    <span>Sessions</span>
                </div>
                <div id="history-status" class="status-line">Loading sessions...</div>
                <div id="session-list" class="session-list"></div>
            </section>

            <section class="detail-column" aria-label="Workout detail">
                <div class="column-title">
                    <span>Session details</span>
                </div>
                <div id="detail-content" class="detail-shell">
                    <div class="empty-state">Select a session.</div>
                </div>
            </section>
        </main>
    </div>
</x-mcp::app>
