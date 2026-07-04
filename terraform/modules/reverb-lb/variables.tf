variable "project" {
  type = string
}

variable "vpc_id" {
  type = string
}

variable "public_subnet_ids" {
  type = list(string)
}

variable "target_port" {
  description = "Port artisan reverb:start listens on (matches --port in the ECS task's container command, mirrors 8080 used locally)."
  type        = number
  default     = 8080
}

variable "certificate_arn" {
  description = "ACM certificate covering the browser-facing WebSocket hostname (ws.<domain>) for the TLS:443 listener — issued/validated in environments/prod/domain.tf."
  type        = string
}
