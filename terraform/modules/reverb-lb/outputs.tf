output "dns_name" {
  description = "Prod equivalent of local VITE_REVERB_HOST — the browser-facing WebSocket host."
  value       = aws_lb.this.dns_name
}

output "target_group_arn" {
  value = aws_lb_target_group.this.arn
}
