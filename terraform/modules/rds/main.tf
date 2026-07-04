resource "aws_db_subnet_group" "this" {
  name       = "${var.project}-rds"
  subnet_ids = var.private_subnet_ids
}

resource "aws_security_group" "this" {
  name_prefix = "${var.project}-rds-"
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
  from_port                = 3306
  to_port                  = 3306
  protocol                 = "tcp"
  security_group_id        = aws_security_group.this.id
  source_security_group_id = var.allowed_security_group_ids[count.index]
}

resource "aws_db_instance" "this" {
  identifier     = "${var.project}-mariadb"
  engine         = "mariadb"
  engine_version = var.engine_version

  instance_class    = var.instance_class
  allocated_storage = var.allocated_storage
  storage_encrypted = true

  db_name  = var.db_name
  username = var.username
  password = var.password

  db_subnet_group_name   = aws_db_subnet_group.this.name
  vpc_security_group_ids = [aws_security_group.this.id]

  multi_az            = var.multi_az
  publicly_accessible = false
  skip_final_snapshot = true # first-cut default; revisit alongside multi_az for a real launch

  backup_retention_period = 7
}
