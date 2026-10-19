# TEMPORARY destroy-only stub. The real domain.tf is in domain.tf.off; the
# Cloudflare records were removed from state (the token available locally cannot
# manage the street-bites.org zone) and are deleted by hand in the dashboard.
locals {
  mail_from_address = "noreply@${var.domain}"
}

resource "aws_acm_certificate" "this" {
  domain_name = var.domain
  subject_alternative_names = [
    "www.${var.domain}",
    "ws.${var.domain}",
  ]
  validation_method = "DNS"

  lifecycle {
    create_before_destroy = true
  }
}

resource "aws_acm_certificate_validation" "this" {
  certificate_arn = aws_acm_certificate.this.arn
}
