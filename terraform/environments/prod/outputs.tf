output "app_url" {
  description = "Canonical public URL (APP_URL) — https on the ALB's ACM cert; the apex and plain-HTTP both redirect/resolve here via Cloudflare DNS (domain.tf)."
  value       = "https://www.${var.domain}"
}

output "alb_dns_name" {
  description = "The ALB's own DNS name — what the Cloudflare apex/www CNAMEs point at. Not for browsers (the cert only covers the domain)."
  value       = module.alb.dns_name
}

output "reverb_browser_host" {
  description = "Browser-facing WebSocket host: set the VITE_REVERB_HOST repo variable to this (with VITE_REVERB_PORT=443, VITE_REVERB_SCHEME=https) and publish a release to rebake the JS bundle. null while var.enable_reverb is false."
  value       = var.enable_reverb ? "ws.${var.domain}" : null
}

output "reverb_dns_name" {
  description = "The Reverb NLB's own DNS name — the server-side broadcast hairpin target (REVERB_HOST) and what the Cloudflare ws CNAME points at. null while var.enable_reverb is false."
  value       = one(module.reverb_lb[*].dns_name)
}

output "ecr_repository_url" {
  description = "Consumed by release-deploy.yml to know where to push images."
  value       = module.ecr.repository_url
}

output "ecs_cluster_name" {
  value = module.ecs_cluster.cluster_name
}

output "web_service_name" {
  value = module.web.service_name
}

output "reverb_service_name" {
  description = "null while var.enable_reverb is false."
  value       = one(module.reverb[*].service_name)
}

output "queue_worker_service_name" {
  value = module.queue_worker.service_name
}
