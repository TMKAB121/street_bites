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
  description = "0 = no NAT gateway (the cost default: ~$33/mo plus per-GB processing, for a site with near-zero traffic). Tasks then run in the public subnets with public IPs and reach the internet through the IGW; inbound stays closed at the security groups. 1 = single shared NAT (requires moving tasks back to private subnets); var.az_count = one per AZ for HA."
  type        = number
  default     = 0
}
