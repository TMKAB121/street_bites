# A domain identity (not a single email address): once the three Easy-DKIM
# CNAMEs exist in DNS (created by environments/prod/domain.tf at Cloudflare),
# SES verifies the domain automatically — no click-a-link step — and the app
# may send as *any* address under it (MAIL_FROM_ADDRESS picks one). DKIM also
# aligns with the from-domain for DMARC, unlike the earlier bare-address
# identity that sent "from" Gmail with unaligned signatures.
#
# Separately, a fresh AWS account starts in the SES *sandbox*: it may only
# deliver *to* verified addresses. Requesting production access is a one-time
# support-case action in the SES console — Terraform cannot do it. Until it's
# granted, sign-up/sign-in works only for recipient addresses you've verified.
resource "aws_sesv2_email_identity" "domain" {
  email_identity = var.domain
}
