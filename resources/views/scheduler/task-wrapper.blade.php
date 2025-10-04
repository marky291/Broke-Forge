#!/bin/bash
#
# BrokeForge Scheduler - Task Wrapper Script
# Task ID: {{ $taskId }}
# Wraps task execution and sends heartbeat to BrokeForge
#

# Configuration
BROKEFORGE_URL='{{ $appUrl }}'
SERVER_ID='{{ $serverId }}'
TASK_ID='{{ $taskId }}'
SCHEDULER_TOKEN='{{ $schedulerToken }}'
COMMAND='{{ $command }}'
TIMEOUT={{ $timeout }}
API_ENDPOINT="${BROKEFORGE_URL}/api/servers/${SERVER_ID}/scheduler/runs"

# Security: Enforce HTTPS for API communication (allow HTTP for local development)
if [[ ! "${API_ENDPOINT}" =~ ^https:// ]] && [[ ! "${API_ENDPOINT}" =~ ^http://localhost ]] && [[ ! "${API_ENDPOINT}" =~ ^http://127\.0\.0\.1 ]] && [[ ! "${API_ENDPOINT}" =~ ^http://192\.168\. ]] && [[ ! "${API_ENDPOINT}" =~ ^http://10\. ]] && [[ ! "${API_ENDPOINT}" =~ ^http://172\.(1[6-9]|2[0-9]|3[0-1])\. ]]; then
    logger -t brokeforge-scheduler "SECURITY WARNING: Refusing to send data over insecure connection. API endpoint must use HTTPS."
    echo "ERROR: API endpoint must use HTTPS for security" >&2
    exit 1
fi

# Capture start time
START_TIME=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
START_MS=$(date +%s%3N)

# Execute command with timeout and capture output
OUTPUT_FILE=$(mktemp)
ERROR_FILE=$(mktemp)

timeout ${TIMEOUT} bash -c "${COMMAND}" > "${OUTPUT_FILE}" 2> "${ERROR_FILE}"
EXIT_CODE=$?

# Capture end time and calculate duration
END_TIME=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
END_MS=$(date +%s%3N)
DURATION=$((END_MS - START_MS))

# Read output files
STDOUT=$(cat "${OUTPUT_FILE}" | jq -Rs . 2>/dev/null || echo '""')
STDERR=$(cat "${ERROR_FILE}" | jq -Rs . 2>/dev/null || echo '""')

# Cleanup temp files
rm -f "${OUTPUT_FILE}" "${ERROR_FILE}"

# Build JSON payload
JSON_PAYLOAD=$(cat <<JSON_END
{
  "task_id": ${TASK_ID},
  "started_at": "${START_TIME}",
  "completed_at": "${END_TIME}",
  "exit_code": ${EXIT_CODE},
  "output": ${STDOUT},
  "error_output": ${STDERR},
  "duration_ms": ${DURATION}
}
JSON_END
)

# Send heartbeat to BrokeForge with authentication token
curl -X POST "${API_ENDPOINT}" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -H "X-Scheduler-Token: ${SCHEDULER_TOKEN}" \
  -d "${JSON_PAYLOAD}" \
  --max-time {{ config('scheduler.script_timeout') }} \
  --silent \
  --fail

# Log result
HEARTBEAT_EXIT=$?
if [ $HEARTBEAT_EXIT -eq 0 ]; then
  logger -t brokeforge-scheduler "Task #${TASK_ID} completed (exit: ${EXIT_CODE}) - Heartbeat sent successfully"
else
  logger -t brokeforge-scheduler "Task #${TASK_ID} completed (exit: ${EXIT_CODE}) - Heartbeat failed (exit: ${HEARTBEAT_EXIT})"
fi

# Exit with command's exit code, not heartbeat exit code
exit $EXIT_CODE
