const fs = require('fs');
const path = require('path');

function replaceInDir(dir) {
    fs.readdirSync(dir).forEach(file => {
        const fullPath = path.join(dir, file);

        if (fs.statSync(fullPath).isDirectory()) {
            replaceInDir(fullPath);
        } else if (fullPath.endsWith('.tsx') || fullPath.endsWith('.ts') || fullPath.endsWith('.jsx') || fullPath.endsWith('.js')) {
            let content = fs.readFileSync(fullPath, 'utf8');

            if (content.includes('react-router-dom')) {
                // Replace import { Link, NavLink, etc } from 'react-router-dom'
                // with import { Link } from '@inertiajs/react'
                // For NavLink, we can alias it or just use Link
                // A simple string replace for the import path
                content = content.replace(/'react-router-dom'/g, "'@inertiajs/react'");
                content = content.replace(/"react-router-dom"/g, "'@inertiajs/react'");
                fs.writeFileSync(fullPath, content, 'utf8');
                console.log('Updated ' + fullPath);
            }
        }
    });
}

replaceInDir('resources/js/velzone');
