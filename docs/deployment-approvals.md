# Deployment Approvals

Production deployment policies default to approval-required for developers.

Approval records store a fingerprint of website, component, resource link, branch and commit. If any of those change before approval, the approval is invalidated.

Owners can approve or reject. Rejections and expirations leave the deployment cancelled rather than silently retrying.

