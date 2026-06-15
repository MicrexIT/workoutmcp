# Workout Memory

> Workout Memory is an MCP (Model Context Protocol) server that gives AI assistants such as ChatGPT, Claude, or any MCP client a permanent, structured memory for workouts: lifting, yoga, spinning, mobility, conditioning, and more. Users log workouts by describing them in natural language; the server resolves exercise mentions, stores sets, reps, loads, durations and distances when relevant, and answers questions about training history.

Product site: {{ $publicUrl }}

## Connect an AI client

- MCP endpoint (streamable HTTP): {{ $mcpUrl }}
- Authentication: OAuth 2.1 with dynamic client registration. Authorization server metadata: {{ $publicUrl }}/.well-known/oauth-authorization-server
- A Workout Memory account at {{ $publicUrl }} is required to authorize access.

## Client setup

- ChatGPT: Settings -> Apps & Connectors -> Advanced settings -> enable Developer mode, then back in Apps & Connectors choose Create, name it "Workout Memory", use the MCP endpoint above with OAuth authentication.
- Claude (claude.ai): Settings -> Connectors -> Add custom connector, paste the MCP endpoint, then Connect and sign in.
- Claude Code: `claude mcp add --transport http workout-memory {{ $mcpUrl }}` then run `/mcp` to authenticate.
- Other MCP clients: point the client at the MCP endpoint; OAuth metadata is discoverable from the URLs above.

## What the server exposes

19 tools for logging and recalling workouts, including: logging completed workouts from natural language, live in-progress sessions (start, append exercises and notes, finish), exercise search and resolution with per-user phrase memory, per-exercise history with best efforts when relevant, training summaries for planning, durable user context (goals, injuries, available equipment), workout update, merge and delete, and revocable public share links for completed workouts (share_workout, on explicit user request only).

## Notes for agents

- Send raw user phrasing when logging; the server resolves it, prefers existing exercises, and reports assumptions and auto-created exercises in its responses.
- Live sessions auto-finish after 18 hours of inactivity.
- This file and the landing page at {{ $publicUrl }} are the canonical public references for connecting.
