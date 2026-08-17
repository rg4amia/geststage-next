const fs = require('fs');
const path = require('path');

function replaceInDir(dir) {
    fs.readdirSync(dir).forEach(file => {
        const fullPath = path.join(dir, file);

        if (fs.statSync(fullPath).isDirectory()) {
            replaceInDir(fullPath);
        } else if (fullPath.endsWith('.tsx') || fullPath.endsWith('.ts') || fullPath.endsWith('.jsx') || fullPath.endsWith('.js')) {
            let content = fs.readFileSync(fullPath, 'utf8');

            if (content.includes('@inertiajs/react')) {
                // Ignore app.tsx since it's not in these folders, but just in case
                if (!fullPath.includes('app.tsx')) {
                    content = content.replace(/'@inertiajs\/react'/g, "'react-router-dom'");
                    content = content.replace(/"@inertiajs\/react"/g, "'react-router-dom'");
                    fs.writeFileSync(fullPath, content, 'utf8');
                    console.log('Reverted ' + fullPath);
                }
            }
        }
    });
}

replaceInDir('resources/js/velzone/pages');
replaceInDir('resources/js/velzone/Components');
replaceInDir('resources/js/velzone/Layouts');
