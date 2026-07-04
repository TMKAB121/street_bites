output "alb_dns_name" {
  description = "Public URL for the app (plain HTTP — no custom domain/TLS in this first cut)."
  value       = module.alb.dns_name
}

output "reverb_dns_name" {
  description = "Browser-facing WebSocket host, the prod equivalent of local VITE_REVERB_HOST."
  value       = module.reverb_lb.dns_name
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
  value = module.reverb.service_name
}

output "queue_worker_service_name" {
  value = module.queue_worker.service_name
}
