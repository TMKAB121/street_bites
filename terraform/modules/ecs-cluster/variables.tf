variable "project" {
  type = string
}

# Container Insights bills per-task custom metrics — real money on a cluster
# this small, and worth nothing while nothing is wrong. Off by default; flip it
# to true (apply, diagnose, flip back) when a problem needs the metrics. Task
# logs go to CloudWatch Logs either way, so this doesn't blind the cluster.
variable "container_insights" {
  type    = bool
  default = false
}
