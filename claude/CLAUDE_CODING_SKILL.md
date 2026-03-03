## Workflow Orchestration

### 1. Plan Mode Default
- Enter plan mode for ANY non-trivial task (3+ steps or architectural decisions)
- If something goes sideways, STOP and re-plan immediately – don't keep pushing
- Use plan mode for verification steps, not just building
- Write detailed specs upfront to reduce ambiguity

### 2. Subagent Strategy
- Use subagents liberally to keep main context window clean
- Offload research, exploration, and parallel analysis to subagents
- For complex problems, throw more compute at it via subagents
- One task per subagent for focused execution
- Launch subagents in parallel when tasks are independent

### 3. Self-Improvement Loop
- After ANY correction from the user: update `tasks/lessons.md` with the pattern
- Write rules for yourself that prevent the same mistake
- Ruthlessly iterate on these lessons until mistake rate drops
- Review lessons at session start for relevant project

### 4. Verification Before Done
- Never mark a task complete without proving it works
- Diff behavior between main and your changes when relevant
- Ask yourself: "Would a staff engineer approve this?"
- Run tests, check logs, demonstrate correctness

### 5. Demand Elegance (Balanced)
- For non-trivial changes: pause and ask "is there a more elegant way?"
- If a fix feels hacky: "Knowing everything I know now, implement the elegant solution"
- Skip this for simple, obvious fixes – don't over-engineer
- Challenge your own work before presenting it

### 6. Autonomous Bug Fixing
- When given a bug report: just fix it. Don't ask for hand-holding
- Point at logs, errors, failing tests – then resolve them
- Zero context switching required from the user
- Go fix failing CI tests without being told how

### 7. Session Resume Protocol
- At session start: check `tasks/todo.md` for in-progress work
- Read `CLAUDE_PROJECT.md` for current project context before doing anything
- Read `CLAUDE_PROJECT_FRONTEND.md` for current project context before doing anything with frontend
- Don't ask the user "what were we doing?" — figure it out from the files

### 8. Scope Guard
- If a task grows beyond its original scope, STOP and surface it
- Say: "This requires X additional changes. Proceed?"
- Never silently expand scope — scope creep is invisible debt

### 9. Read Before Modify
- Always read the current file state before any edit
- Never assume structure from filename or partial context
- Explicitly state what you're changing and why

### 10. Lessons vs Project Knowledge Criteria
- `tasks/lessons.md`: patterns of YOUR mistakes (process, assumptions, errors)
- `CLAUDE_PROJECT.md`: stable project knowledge (architecture, gotchas, decisions)
- `CLAUDE_PROJECT_FRONTEND.md`: stable project knowledge of frontend (architecture, gotchas, decisions)
- Neither: one-off implementation details that won't recur

---

## Task Management

1. **Plan First**: Write plan to `tasks/todo.md` with checkable items
2. **Verify Plan**: Check in before starting implementation — but only if the task is architectural or touches more than 3 files; bugs and clearly scoped local tasks should be fixed autonomously
3. **Track Progress**: Mark items complete as you go
4. **Explain Changes**: High-level summary at each step
5. **Document Results**: Add review section to `tasks/todo.md`
6. **Capture Lessons**: Update `tasks/lessons.md` after corrections
7. **Update Project Knowledge**: After completing a task, review `CLAUDE_PROJECT.md` and `CLAUDE_PROJECT_FRONTEND.md` and update or add any significant knowledge about the project (architectural decisions, non-obvious patterns, recurring gotchas, domain rules). Do NOT include one-off details or trivial implementation specifics.

---

## Project Context

- Always consult `CLAUDE_PROJECT.md` and `CLAUDE_PROJECT_FRONTEND.md` for project-specific knowledge: architecture, domain rules, established patterns, and key decisions made during development.
- Treat `CLAUDE_PROJECT.md` and `CLAUDE_PROJECT_FRONTEND.md` as the authoritative source of project truth — if it contradicts your assumptions, trust `CLAUDE_PROJECT.md` and `CLAUDE_PROJECT_FRONTEND.md`.

---

## Core Principles

- **Simplicity First**: Make every change as simple as possible. Impact minimal code.
- **No Laziness**: Find root causes. No temporary fixes. Senior developer standards.
- **Minimal Impact**: Changes should only touch what's necessary. Avoid introducing bugs.
