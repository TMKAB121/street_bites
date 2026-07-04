variable "domain" {
  description = "Domain to verify as an SES domain identity (DKIM). The app sends auth codes as an address under it (MAIL_FROM_ADDRESS)."
  type        = string
}
