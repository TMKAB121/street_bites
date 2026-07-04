# GitHub Actions authenticates via OIDC federation — no long-lived AWS access
# keys stored in GitHub Secrets. AWS validates the token against GitHub's own
# TLS chain; the thumbprint below is fetched dynamically rather than
# hardcoded so it tracks GitHub's certificate rotation automatically.

data "tls_certificate" "github" {
  url = "https://token.actions.githubusercontent.com"
}

resource "aws_iam_openid_connect_provider" "github" {
  url             = "https://token.actions.githubusercontent.com"
  client_id_list  = ["sts.amazonaws.com"]
  thumbprint_list = [data.tls_certificate.github.certificates[0].sha1_fingerprint]
}

# --- gha-terraform-role: assumed by terraform-plan.yml / terraform-apply.yml ---
#
# Trusted for both `pull_request` (plan) and pushes to the repo's main branch
# (apply) — this repo's main/default branch is `develop`, not `main`. Note:
# this means a PR-triggered plan run holds the same broad infra permissions as
# an apply on develop — an accepted simplification for a single-environment
# first cut. Tightening this (e.g. a read-only role for plan) is a reasonable
# fast-follow once the pipeline is proven out.
resource "aws_iam_role" "gha_terraform" {
  name = "${var.project}-gha-terraform"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect    = "Allow"
      Principal = { Federated = aws_iam_openid_connect_provider.github.arn }
      Action    = "sts:AssumeRoleWithWebIdentity"
      Condition = {
        StringEquals = {
          "token.actions.githubusercontent.com:aud" = "sts.amazonaws.com"
        }
        StringLike = {
          "token.actions.githubusercontent.com:sub" = [
            "repo:${var.github_org}/${var.github_repo}:ref:refs/heads/${var.github_main_branch}",
            "repo:${var.github_org}/${var.github_repo}:pull_request",
          ]
        }
      }
    }]
  })
}

# --- gha-deploy-role: assumed only by release-deploy.yml -------------------
#
# Scoped to the `production` GitHub Environment rather than the `release`
# event itself — GitHub's OIDC token doesn't expose "release" as a distinct
# ref type in the `sub` claim, but an Environment gives a clean, matchable
# subject and the option to add required reviewers later.
resource "aws_iam_role" "gha_deploy" {
  name = "${var.project}-gha-deploy"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect    = "Allow"
      Principal = { Federated = aws_iam_openid_connect_provider.github.arn }
      Action    = "sts:AssumeRoleWithWebIdentity"
      Condition = {
        StringEquals = {
          "token.actions.githubusercontent.com:aud" = "sts.amazonaws.com"
          "token.actions.githubusercontent.com:sub" = "repo:${var.github_org}/${var.github_repo}:environment:production"
        }
      }
    }]
  })
}
