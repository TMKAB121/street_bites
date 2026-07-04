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
  description = "Container port the web service's nginx listens on (docker/nginx.conf)."
  type        = number
  default     = 8080
}

variable "health_check_path" {
  description = "Laravel's default health-check route (bootstrap/app.php `health: '/up'`)."
  type        = string
  default     = "/up"
}

variable "certificate_arn" {
  description = "ACM certificate (same region as the ALB) for the HTTPS:443 listener — issued/validated in environments/prod/domain.tf."
  type        = string
}
