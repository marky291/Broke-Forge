#
# BrokeForge Monitoring - Metrics Collection Script
# Collects CPU, Memory, and Storage metrics and sends them to BrokeForge
#

# Configuration
BROKEFORGE_URL="{{ $appUrl }}"
SERVER_ID="{{ $serverId }}"
MONITORING_TOKEN="{{ $monitoringToken }}"
API_ENDPOINT="${BROKEFORGE_URL}/api/servers/${SERVER_ID}/metrics"

# Collect CPU usage (percentage)
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print 100 - $1}' 2>/dev/null || echo "0")

# Collect Memory usage (used and total in MB)
MEMORY_INFO=$(free -m | grep Mem 2>/dev/null || echo "Mem: 0 0")
MEMORY_TOTAL=$(echo $MEMORY_INFO | awk '{print $2}' || echo "0")
MEMORY_USED=$(echo $MEMORY_INFO | awk '{print $3}' || echo "0")
MEMORY_PERCENTAGE=$(echo "scale=2; ($MEMORY_USED / $MEMORY_TOTAL) * 100" | bc 2>/dev/null || echo "0")

# Collect Storage usage (used and total in GB for root partition)
STORAGE_INFO=$(df -BG / 2>/dev/null | tail -1 || echo "/ 0G 0G 0G 0% /")
STORAGE_TOTAL=$(echo $STORAGE_INFO | awk '{print $2}' | sed 's/G//' || echo "0")
STORAGE_USED=$(echo $STORAGE_INFO | awk '{print $3}' | sed 's/G//' || echo "0")
STORAGE_PERCENTAGE=$(echo $STORAGE_INFO | awk '{print $5}' | sed 's/%//' || echo "0")

# Build JSON payload
JSON_PAYLOAD=$(cat <<EOJSON
{
  "cpu_usage": ${CPU_USAGE},
  "memory_total_mb": ${MEMORY_TOTAL},
  "memory_used_mb": ${MEMORY_USED},
  "memory_usage_percentage": ${MEMORY_PERCENTAGE},
  "storage_total_gb": ${STORAGE_TOTAL},
  "storage_used_gb": ${STORAGE_USED},
  "storage_usage_percentage": ${STORAGE_PERCENTAGE},
  "collected_at": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
}
EOJSON
)

# Send metrics to BrokeForge with authentication token
RESPONSE=$(curl -X POST "${API_ENDPOINT}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Monitoring-Token: ${MONITORING_TOKEN}" \
  -d "${JSON_PAYLOAD}" \
  --max-time {{ config('monitoring.script_timeout') }} \
  --write-out "\n%{http_code}" \
  --silent 2>&1)

# Extract HTTP status code and response body
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$RESPONSE" | sed '$d')

# Log result with HTTP status code
if [ "$HTTP_CODE" = "201" ] || [ "$HTTP_CODE" = "200" ]; then
  logger -t brokeforge-monitoring "Metrics successfully sent to BrokeForge (CPU: ${CPU_USAGE}%, Memory: ${MEMORY_PERCENTAGE}%, Storage: ${STORAGE_PERCENTAGE}%)"
  exit 0
else
  logger -t brokeforge-monitoring "Failed to send metrics to BrokeForge (HTTP ${HTTP_CODE}): ${RESPONSE_BODY}"
  exit 1
fi
