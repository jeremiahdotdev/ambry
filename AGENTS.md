# Agent Instructions

## OpenAI API Approval

Do not submit anything that requires an OpenAI API call without asking Jeremiah directly first and receiving explicit approval in the conversation.

This applies to all OpenAI usage, including:

- Direct Responses API calls.
- OpenAI Batch API submissions.
- Image generation or image editing calls.
- File uploads meant for OpenAI processing.
- Test runs that would call OpenAI, even for one record.
- Retry, resume, import, or sync steps that would trigger new OpenAI work.

Before any such action, state exactly what will be submitted, the model, the approximate record count, and whether it is direct API or Batch API. Wait for approval before running it.

Local-only steps are allowed without extra approval when they do not call OpenAI, such as building JSONL files, dry runs, parsing saved OpenAI responses, importing already-downloaded output files, or syncing existing local artifacts to the database.
