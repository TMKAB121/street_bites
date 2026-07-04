variable "project" {
  type = string
}

variable "vpc_cidr" {
  type    = string
  default = "10.0.0.0/16"
}

variable "az_count" {
  description = "Number of availability zones to spread public/private subnets across."
  type        = number
  default     = 2
}

variable "nat_gateway_count" {
  description = "1 = single shared NAT gateway (cost-optimized default, single point of failure across AZs). Set to var.az_count for one-per-AZ HA — a fast-follow, not the day-one default."
  type        = number
  default     = 1
}
