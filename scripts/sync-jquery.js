const fs = require("node:fs");
const path = require("node:path");

const sourceFile = path.join(__dirname, "..", "node_modules", "jquery", "dist", "jquery.min.js");
const targetDir = path.join(__dirname, "..", "assets", "vendor", "jquery");
const targetFile = path.join(targetDir, "jquery.min.js");

try {
  if (!fs.existsSync(sourceFile)) {
    throw new Error("jquery.min.js was not found in node_modules. Run npm install first.");
  }

  fs.mkdirSync(targetDir, { recursive: true });
  fs.copyFileSync(sourceFile, targetFile);
  console.log(`jQuery copied to ${targetFile}`);
} catch (error) {
  console.error(`Failed to sync jQuery: ${error.message}`);
  process.exit(1);
}
