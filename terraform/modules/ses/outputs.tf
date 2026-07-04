output "identity_arn" {
  description = "Feed into modules/iam-task-roles so the app's task role may send as this identity."
  value       = aws_sesv2_email_identity.domain.arn
}

output "dkim_tokens" {
  description = "The three Easy-DKIM tokens — each becomes a CNAME `{token}._domainkey.{domain}` → `{token}.dkim.amazonses.com` (created in Cloudflare by environments/prod/domain.tf). SES flips the identity to verified once it sees them."
  value       = aws_sesv2_email_identity.domain.dkim_signing_attributes[0].tokens
}
