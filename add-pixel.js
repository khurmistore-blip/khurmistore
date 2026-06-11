const fs=require('fs'),path=require('path');
const TAG='<script src="/js/meta-pixel.js"></script>';
function walk(d){for(const n of fs.readdirSync(d)){const p=path.join(d,n);const s=fs.statSync(p);
if(s.isDirectory()){if(n!=='node_modules'&&n!=='.git')walk(p);}
else if(n.toLowerCase().endsWith('.html')){let h=fs.readFileSync(p,'utf8');
if(h.includes('meta-pixel.js'))continue;
const m=h.match(/<\/head>/i);
if(!m){console.log('NO </head>:',p);continue;}
h=h.replace(m[0],TAG+'\n'+m[0]);fs.writeFileSync(p,h);console.log('FIXED:',p);}}}
walk('.');
