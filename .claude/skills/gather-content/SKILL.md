---
name: gather-content
description: Draft a Street Bites news/event post from a source URL or pasted material — a clean-room rewrite in the house writing style that links back to the source — and deliver ready-to-paste Markdown plus a processed cover (from a user-owned image or generated via Gemini Nano Banana; never from the source) for the /admin/news form. Use when the user runs /gather-content, or asks to "gather content", "write a news post from <url>", or "turn this into a post".
---

# /gather-content — source → styled post → admin-form-ready files

Turn a source the user provides into a post drafted in their writing style,
**verified with them for accuracy**, then delivered as copy-pasteable files.
The user pastes the result into `/admin/news` themselves — this workflow
never logs in, never submits a form, never publishes.

## Inputs

`$ARGUMENTS` is the source: a URL, or pasted source material, optionally
followed by notes (angle, emphasis, "this is an event", etc.) and optionally
a path to a **cover image the user owns**. If no source was given, ask for
one and stop.

**Copyright stance (applies to every step):** the source is reference
material only — the draft is a clean-room rewrite (facts extracted, wording
original), the body always links back to the source, and **no image is ever
taken from the source**. A cover comes from the user's own image or is
**AI-generated** (Gemini Nano Banana — see *Cover generation setup*); a
cover-less post falls back to the app's default branded og card.

## Workflow

### 1. Preflight — style samples must exist

Check `.claude/skills/gather-content/samples/` for at least one `.md` file
other than `README.md`. If empty, stop and tell the user to add writing
samples there first — the style contract is samples-driven, so there is no
drafting without them.

### 2. Draft + generate — spawn the content-writer agent

Spawn the **content-writer** agent (`subagent_type: "content-writer"`,
`run_in_background: false`) with:
- the source URL or the pasted material verbatim,
- the user's notes, and the user-supplied cover image path if they gave one,
- an instruction to draft the post **and go straight to writing the
  deliverable files** (no pre-approval gate) to
  `~/Desktop/street-bites-drafts/<post-slug>/`:
  - `post.md` — frontmatter (`title`, and `event_date` / `event_location`
    only when set — `event_date` written as **`MM/DD/YYYY`**, matching what
    the admin form's native date picker displays/expects when typed
    manually) followed by the body Markdown, so each `/admin/news` field is
    one copy-paste.
  - `cover.jpg` — from a **user-supplied image**, or, when none was given,
    **generated with the Gemini image API** per the agent's *Cover
    generation* section (never anything from the source URL): mime-verified
    (jpeg/png/webp), centre-cropped to **1200×630 JPEG** via `sips`, ≤ 5 MB.
    No user image and no API key → no cover file, and that's a normal
    outcome, not a failure. A failed cover is skipped and reported, never
    a blocker.
- a reminder to still report every field, the cover decision, the source
  link it placed in the body, and its open accuracy questions back in its
  structured report (used for the review step below).

### 3. Review checkpoint — "is this good?", not a gate

Show the user the generated files' actual content (title, event date,
event location, body, and the cover image — for a generated cover, also
the prompt the agent used, so they can steer a regeneration) plus the
agent's open accuracy questions. Confirm two things
yourself before asking: the body contains the **source link**, and no run
of wording is lifted from the source (spot-check a few sentences against
it). Use AskUserQuestion framed as "does this look right?" — this is a
review of real output, not a pre-generation approval gate. Default
expectation is a quick yes; only loop if something's off.

On revisions: send the feedback to the **same** agent via SendMessage (it
keeps the source context and the file paths) and have it **rewrite the
files in place**, then repeat this checkpoint with the updated version.
Loop until the user is satisfied.

### 4. Handoff

Relay the final field-by-field, paste-ready summary with the file paths
(event date shown as `MM/DD/YYYY`). Remind the user:
- fields map to `#news-title`, `#news-body`, `#news-event-date`,
  `#news-event-location`, `#news-cover` on `/admin/news`;
- new posts save as **drafts** — Publish is a separate button they click
  themselves.

### 5. Optional — fill (never submit) the open form

Only if the user explicitly asks: they will already be signed in with their
real Chrome sitting on `/admin/news`. Use the `claude-in-chrome` tools to
populate — `form_input` on `#news-title`, `#news-body`, `#news-event-date`
(value `YYYY-MM-DD`), `#news-event-location`, and `file_upload` for
`#news-cover` (then wait for Livewire's "Reading image…" indicator to
clear). **Never click Save, Publish, Unpublish, Remove cover, or Delete** —
the user reviews and submits.

## Cover generation setup (one-time, for reference)

Generated covers use the Gemini API's Nano Banana model
(`gemini-2.5-flash-image`) and activate only when an API key is present —
no key, no generation, no error. A consumer Gemini subscription is **not**
API access; the key comes from Google AI Studio:

1. https://aistudio.google.com → **Get API key** (free to create; image
   generation is billed per image on the paid tier, roughly $0.04 each).
2. Store it in the macOS Keychain (preferred — nothing on disk):
   ```bash
   security add-generic-password -s street-bites-gemini -a "$USER" -w '<the key>'
   ```
   (or export `GEMINI_API_KEY` in the shell environment as a fallback).

The agent looks the key up itself; never paste the key into a prompt, a
file in the repo, or this skill.

## Form contract (for reference)

| Field | Constraint |
|---|---|
| title | required, ≤ 255 |
| body | required, ≤ 20,000 chars, Markdown (safe-mode rendered — no raw HTML) |
| event_date | optional, native `<input type="date">`; presence makes the post an event. Underlying value is ISO `YYYY-MM-DD` (what `form_input` in step 5 must set), but the picker's visible/typed segments are locale-formatted — `MM/DD/YYYY` for this app — so `post.md` and the handoff summary use `MM/DD/YYYY` since the user is typing it by hand |
| event_location | optional, ≤ 255 |
| cover | optional, jpeg/png/webp ≤ 5 MB; app crops to 1200×630 JPEG (also the og:image). **User-owned or AI-generated only** — never sourced from the article/listing; no cover → the app's default branded card is the og:image |
