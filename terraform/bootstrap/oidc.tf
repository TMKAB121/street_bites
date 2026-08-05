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

locals {
  # Every spelling of the OIDC `sub` claim prefix that CI might legitimately
  # present, because GitHub changed this format underneath us.
  #
  # GitHub now appends the owner's and repo's immutable numeric IDs
  # ("repo:OWNER@6655240/REPO@1274615786:ref:...") so that renaming an account
  # or repository can't hand this role to whoever claims the freed-up name.
  # Matching only the legacy name-only form is exactly what started failing
  # every run with "Not authorized to perform sts:AssumeRoleWithWebIdentity"
  # — the same break the sibling louisburg-community-choir repo hit on
  # 2026-07-30 and fixed the same way.
  #
  # Both forms are listed so a revert of that rollout doesn't break CI a second
  # time, and both spellings of the owner login are listed because the policy
  # that worked here historically used `tmkab121` while the sibling repo's
  # working policy uses the account's actual `TMKAB121` casing — IAM string
  # matching is case-sensitive, so rather than bet on which one GitHub sends,
  # accept both. Every entry is an exact string with no wildcards, so a longer
  # list is not a looser policy.
  github_sub_prefixes = flatten([
    for owner in distinct([var.github_org, lower(var.github_org)]) : [
      "repo:${owner}@${var.github_owner_id}/${var.github_repo}@${var.github_repo_id}",
      "repo:${owner}/${var.github_repo}",
    ]
  ])
}

# --- gha-terraform-role: assumed by terraform-plan.yml / terraform-apply.yml ---
#
# Trusted for `pull_request` (plan), pushes to `var.github_main_branch`
# (`main` — deliberately not `develop`, this repo's GitHub default/
# integration branch: infra only applies once develop is promoted into
# main), AND the `production-infra` GitHub Environment. That third one is
# required because a job's OIDC `sub` claim becomes
# `repo:OWNER/REPO:environment:NAME` (not the ref-based form) whenever the
# job declares `environment:` — which terraform-apply.yml does, to gate
# applies behind an optional required reviewer. Omitting it produces "Not
# authorized to perform sts:AssumeRoleWithWebIdentity" even though the
# ref-based sub looks right.
#
# Note: this means a PR-triggered plan run holds the same broad infra
# permissions as an apply on main — an accepted simplification for a
# single-environment first cut. Tightening this (e.g. a read-only role for
# plan) is a reasonable fast-follow once the pipeline is proven out.
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
          "token.actions.githubusercontent.com:sub" = flatten([
            for prefix in local.github_sub_prefixes : [
              "${prefix}:ref:refs/heads/${var.github_main_branch}",
              "${prefix}:pull_request",
              "${prefix}:environment:production-infra",
            ]
          ])
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
          # A list is OR-matched — see local.github_sub_prefixes for why there
          # is more than one acceptable spelling.
          "token.actions.githubusercontent.com:sub" = [
            for prefix in local.github_sub_prefixes : "${prefix}:environment:production"
          ]
        }
      }
    }]
  })
}
