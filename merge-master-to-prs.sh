#!/bin/bash

# Ensure you are on the latest master before starting
echo "Fetching latest changes from remote..."
git fetch origin master

# 1. Get a list of all open PR branches targeting master
branches=$(gh pr list --base master --state open --json headRefName --jq '.[].headRefName')

if [ -z "$branches" ]; then
    echo "No open pull requests targeting master found."
    exit 0
fi

echo "Found open PR branches to update:"
echo "$branches"
echo "-----------------------------------"

# 2. Loop through each branch and merge master into it
for branch in $branches; do
    echo "Updating branch: $branch"
    
    # Checkout the feature branch
    git checkout "$branch"
    
    # FIX: Fetch the specific branch and rebase to handle divergence smoothly
    git fetch origin "$branch"
    if ! git rebase origin/"$branch"; then
        echo "Conflict detected during branch synchronization. Skipping rebase..."
        git rebase --abort
    fi
    
    # 3. Merge master into the feature branch
    if git merge origin/master -m "Merge remote-tracking branch 'origin/master' into $branch"; then
        echo "Successfully merged master into $branch"
    else
        echo "CRITICAL: Merge conflict detected on branch '$branch'."
        echo "Please resolve the conflict, commit the changes, and then re-run the script."
        exit 1
    fi
    
    echo "-----------------------------------"
done

# Return to master when finished
git checkout master
echo "All clear! All PR branches processed."