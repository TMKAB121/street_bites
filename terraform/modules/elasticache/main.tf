resource "aws_elasticache_subnet_group" "this" {
  name       = "${var.project}-redis"
  subnet_ids = var.private_subnet_ids
}

resource "aws_security_group" "this" {
  name_prefix = "${var.project}-redis-"
  vpc_id      = var.vpc_id

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  lifecycle {
    create_before_destroy = true
  }
}

resource "aws_security_group_rule" "ingress" {
  count                    = length(var.allowed_security_group_ids)
  type                     = "ingress"
  from_port                = 6379
  to_port                  = 6379
  protocol                 = "tcp"
  security_group_id        = aws_security_group.this.id
  source_security_group_id = var.allowed_security_group_ids[count.index]
}

# Single node, no cluster-mode — matches the current Lando dev setup's Redis
# 7 usage (one instance backing cache/session/queue). Fine for a first cut;
# revisit alongside RDS multi-AZ if uptime requirements grow.
resource "aws_elasticache_cluster" "this" {
  cluster_id         = "${var.project}-redis"
  engine             = "redis"
  engine_version     = var.engine_version
  node_type          = var.node_type
  num_cache_nodes    = 1
  port               = 6379
  subnet_group_name  = aws_elasticache_subnet_group.this.name
  security_group_ids = [aws_security_group.this.id]
}
