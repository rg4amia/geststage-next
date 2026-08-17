const fs = require('fs');
const path = require('path');

function replaceInDir(dir) {
    fs.readdirSync(dir).forEach(file => {
        const fullPath = path.join(dir, file);

        if (fs.statSync(fullPath).isDirectory()) {
            if (fullPath.endsWith(path.join('velzone', 'Routes'))) {
                return;
            }

            replaceInDir(fullPath);
        } else if (fullPath.endsWith('.tsx') || fullPath.endsWith('.ts') || fullPath.endsWith('.jsx') || fullPath.endsWith('.js')) {
            if (fullPath.endsWith(path.join('velzone', 'index.tsx'))) {
                return;
            }

            let content = fs.readFileSync(fullPath, 'utf8');

            if (content.includes('react-router-dom')) {
                content = content.replace(/'react-router-dom'/g, "'@/velzone/inertia-router'");
                content = content.replace(/"react-router-dom"/g, "'@/velzone/inertia-router'");
                fs.writeFileSync(fullPath, content, 'utf8');
                console.log('Updated ' + fullPath);
            }
        }
    });
}

replaceInDir('resources/js/velzone');
