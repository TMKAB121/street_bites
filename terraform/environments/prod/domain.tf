# Everything the street-bites.org domain touches: the ACM certificate, the
# Cloudflare DNS records that validate it, the records that point the domain
# at the load balancers, and the SES DKIM records. DNS is hosted at
# Cloudflare (the registrar), so records are cloudflare_dns_record resources
# — the AWS side never sees the zone.
#
# Every record here is deliberately proxied = false (DNS-only, "grey cloud"):
# TLS terminates at the ALB/NLB with the ACM cert, so Cloudflare is purely a
# DNS host. Flipping the site records to proxied would put Cloudflare's edge
# (and its own cert + SSL-mode matrix) in front of the ALB — a separate
# decision, not a default. The ACM-validation and DKIM records must never be
# proxied or verification breaks.

data "cloudflare_zone" "this" {
  filter = {
    name = var.domain
  }
}

locals {
  cloudflare_zone_id = data.cloudflare_zone.this.zone_id

  # The app sends auth codes as this address — any local part under the
  # verified domain identity works; change it freely.
  mail_from_address = "noreply@${var.domain}"
}

# --- TLS certificate (ALB HTTPS + NLB TLS share it) --------------------------

resource "aws_acm_certificate" "this" {
  domain_name = var.domain
  subject_alternative_names = [
    "www.${var.domain}", # canonical site host (APP_URL)
    "ws.${var.domain}",  # browser-facing Reverb WebSocket host
  ]
  validation_method = "DNS"

  lifecycle {
    create_before_destroy = true
  }
}

resource "cloudflare_dns_record" "acm_validation" {
  for_each = {
    for dvo in aws_acm_certificate.this.domain_validation_options : dvo.domain_name => {
      name  = dvo.resource_record_name
      type  = dvo.resource_record_type
      value = dvo.resource_record_value
    }
  }

  zone_id = local.cloudflare_zone_id
  name    = trimsuffix(each.value.name, ".")
  type    = each.value.type
  content = trimsuffix(each.value.value, ".")
  ttl     = 60
  proxied = false
}

# Blocks the apply until ACM has seen the records and issued the cert, so the
# ALB/NLB listeners (which reference certificate_arn through this resource)
# never race an un-issued certificate.
resource "aws_acm_certificate_validation" "this" {
  certificate_arn         = aws_acm_certificate.this.arn
  validation_record_fqdns = [for record in cloudflare_dns_record.acm_validation : record.name]
}

# --- Site records -------------------------------------------------------------

# Cloudflare flattens CNAMEs at the zone apex automatically, so the apex can
# point at the ALB's DNS name without an A record.
resource "cloudflare_dns_record" "apex" {
  zone_id = local.cloudflare_zone_id
  name    = var.domain
  type    = "CNAME"
  content = module.alb.dns_name
  ttl     = 1 # automatic
  proxied = false
}

resource "cloudflare_dns_record" "www" {
  zone_id = local.cloudflare_zone_id
  name    = "www.${var.domain}"
  type    = "CNAME"
  content = module.alb.dns_name
  ttl     = 1
  proxied = false
}

resource "cloudflare_dns_record" "ws" {
  zone_id = local.cloudflare_zone_id
  name    = "ws.${var.domain}"
  type    = "CNAME"
  content = module.reverb_lb.dns_name
  ttl     = 1
  proxied = false
}

# --- SES DKIM (verifies the domain identity — see modules/ses) ---------------

resource "cloudflare_dns_record" "ses_dkim" {
  count = 3

  zone_id = local.cloudflare_zone_id
  name    = "${module.ses.dkim_tokens[count.index]}._domainkey.${var.domain}"
  type    = "CNAME"
  content = "${module.ses.dkim_tokens[count.index]}.dkim.amazonses.com"
  ttl     = 1
  proxied = false
}
