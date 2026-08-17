import re

with open('app/Http/Middleware/HandleInertiaRequests.php', 'r') as f:
    content = f.read()

# Replace the user array mapping to include roles and permissions
replacement = """            'auth' => [
                'user' => $request->user() ? array_merge($request->user()->toArray(), [
                    'roles' => $request->user()->getRoleNames(),
                ]) : null,
            ],"""

content = re.sub(r'\'auth\'\s*=>\s*\[\s*\'user\'\s*=>\s*\$request->user\(\),\s*\],', replacement, content, flags=re.DOTALL)

with open('app/Http/Middleware/HandleInertiaRequests.php', 'w') as f:
    f.write(content)
