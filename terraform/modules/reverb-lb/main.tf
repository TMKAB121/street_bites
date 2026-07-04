# NLB over a second ALB listener: Reverb is raw WebSocket/TCP traffic, and NLB
# avoids ALB idle-timeout/upgrade edge cases while staying decoupled from the
# web ALB's listener rules.
#
# Two listeners since the HTTPS cutover: TLS:443 terminates wss:// for the
# browser (an https page cannot open plain ws:// — mixed content), while
# TCP:{target_port} stays for the server-side broadcast hairpin from web/
# queue-worker (REVERB_SCHEME=http against the NLB's own DNS name, which the
# cert doesn't cover). Both forward to the same target group.
#
# NLBs don't attach a security group the way ALBs do, so access control here
# happens at the ECS task's own security group (open to 0.0.0.0/0 on
# target_port in environments/prod/main.tf).
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

resource "aws_lb_listener" "tls" {
  load_balancer_arn = aws_lb.this.arn
  port              = 443
  protocol          = "TLS"
  ssl_policy        = "ELBSecurityPolicy-TLS13-1-2-2021-06"
  certificate_arn   = var.certificate_arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.this.arn
  }
}
