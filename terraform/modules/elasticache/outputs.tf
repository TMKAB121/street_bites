output "primary_endpoint" {
  value = aws_elasticache_cluster.this.cache_nodes[0].address
}

output "port" {
  value = aws_elasticache_cluster.this.port
}

output "security_group_id" {
  value = aws_security_group.this.id
}
