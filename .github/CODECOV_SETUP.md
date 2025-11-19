# Codecov Setup (Optional)

To enable code coverage reporting with Codecov:

## Steps

1. **Create a Codecov account**
   - Go to https://codecov.io
   - Sign in with your GitHub account
   - Add your repository

2. **Get your Codecov token**
   - Go to your repository settings on Codecov
   - Copy the repository upload token

3. **Add the token to GitHub Secrets**
   - Go to your GitHub repository
   - Navigate to Settings → Secrets and variables → Actions
   - Click "New repository secret"
   - Name: `CODECOV_TOKEN`
   - Value: Paste your Codecov token
   - Click "Add secret"

4. **Update README badge** (optional)
   Replace the Tests badge with the Codecov badge:
   ```markdown
   [![codecov](https://codecov.io/gh/Gheop/reader/branch/master/graph/badge.svg)](https://codecov.io/gh/Gheop/reader)
   ```

## Notes

- Without the token, the CI will still run successfully but won't upload coverage
- The `continue-on-error: true` flag ensures builds don't fail without Codecov
- Coverage is still generated locally and displayed in CI logs
