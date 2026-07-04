resource "aws_ecr_repository" "this" {
  name = var.name

  # IMMUTABLE means every deploy must push its own uniquely-tagged image (the
  # release tag) rather than overwrite a shared `latest` tag — release-deploy.yml
  # tags images with the release version only, never `latest`.
  image_tag_mutability = "IMMUTABLE"

  image_scanning_configuration {
    scan_on_push = true
  }
}

resource "aws_ecr_lifecycle_policy" "this" {
  repository = aws_ecr_repository.this.name

  policy = jsonencode({
    rules = [{
      rulePriority = 1
      description  = "Expire untagged images after 14 days"
      selection = {
        tagStatus   = "untagged"
        countType   = "sinceImagePushed"
        countUnit   = "days"
        countNumber = 14
      }
      action = { type = "expire" }
    }]
  })
}
