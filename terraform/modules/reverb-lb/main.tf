# NLB over a second ALB listener: Reverb is raw WebSocket/TCP traffic, and NLB
# avoids ALB idle-timeout/upgrade edge cases while staying decoupled from the
# web ALB's listener rules. Revisit once a custom domain + ACM cert exist —
# WSS behind the web ALB becomes viable then.
#
# NLBs don't attach a security group the way ALBs do, so access control here
# happens at the ECS task's own security group (open to 0.0.0.0/0 on
# target_port in environments/prod/main.tf) — consistent with this being a
# no-TLS, no-custom-domain first cut.
resource "aws_lb" "this" {
  name               = "${var.project}-reverb"
  internal           = false
  load_balancer_type = "network"
  subnets            = var.public_subnet_ids
}

resource "aws_lb_target_group" "this" {
  name        = "${var.project}-reverb"
  port        = var.target_port
  protocol    = "TCP"
  vpc_id      = var.vpc_id
  target_type = "ip"

  health_check {
    protocol            = "TCP"
    healthy_threshold   = 2
    unhealthy_threshold = 3
    interval            = 30
  }
}

resource "aws_lb_listener" "tcp" {
  load_balancer_arn = aws_lb.this.arn
  port              = var.target_port
  protocol          = "TCP"

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.this.arn
  }
}
