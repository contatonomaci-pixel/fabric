import { spawn, spawnSync } from "node:child_process";
import { mkdtempSync, readFileSync, writeFileSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { resolve, join } from "node:path";
import { fileURLToPath } from "node:url";
import { randomBytes } from "node:crypto";
const api = fileURLToPath(new URL("../../api", import.meta.url));
const temp = mkdtempSync(join(tmpdir(), "factorycontrol-e2e-"));
const database = join(temp, "database.sqlite");
writeFileSync(database, "");
const env = {
  ...process.env,
  APP_ENV: "local",
  APP_DEBUG: "false",
  APP_KEY: `base64:${randomBytes(32).toString("base64")}`,
  DB_CONNECTION: "sqlite",
  DB_DATABASE: database,
  DB_URL: "",
  CACHE_STORE: "file",
  CACHE_LIMITER: "file",
  CACHE_FILE_PATH: join(temp, "cache"),
  SESSION_DRIVER: "database",
  SESSION_SECURE_COOKIE: "false",
  QUEUE_CONNECTION: "sync",
  MAIL_MAILER: "array",
  DEMO_PASSWORD: process.env.E2E_PASSWORD,
  APP_URL: "http://localhost:8000",
  FRONTEND_URL: "http://localhost:5173",
};
// Perfil criado pelo roteiro de homologação, com MySQL/Redis exclusivos e efêmeros.
if (process.env.FACTORYCONTROL_E2E_PROFILE) {
  const profile = JSON.parse(readFileSync(process.env.FACTORYCONTROL_E2E_PROFILE, "utf8"));
  if (profile.DB_CONNECTION !== "mysql" || profile.DB_HOST !== "127.0.0.1" ||
      profile.DB_DATABASE !== "factorycontrol_pro_e2e_test" || profile.DB_URL) {
    throw new Error("Perfil E2E deve apontar ao banco MySQL local exclusivo de homologação.");
  }
  for (const key of ["DB_CONNECTION", "DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD", "REDIS_CLIENT", "REDIS_HOST", "REDIS_PORT", "REDIS_PREFIX", "CACHE_STORE", "CACHE_PREFIX", "QUEUE_CONNECTION"]) {
    if (typeof profile[key] === "string") env[key] = profile[key];
  }
}
if (!env.DEMO_PASSWORD)
  throw new Error("Execute pelo Playwright para gerar a senha efêmera.");
for (const args of [
  ["artisan", "migrate", "--force"],
  ["artisan", "db:seed", "--class=DemoSeeder", "--force"],
  ["tests/support/seed-e2e-users.php"],
]) {
  const result = spawnSync("php", args, { cwd: api, env, stdio: "inherit" });
  if (result.status !== 0) {
    rmSync(temp, { recursive: true, force: true });
    process.exit(result.status || 1);
  }
}
const child = spawn(
  "php",
  [
    "-S",
    "127.0.0.1:8000",
    "-t",
    resolve(api, "public"),
    resolve(
      api,
      "vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php",
    ),
  ],
  { cwd: resolve(api, "public"), env, stdio: "inherit" },
);
let stopping = false;
function stop() {
  if (stopping) return;
  stopping = true;
  child.kill();
}
process.on("SIGTERM", stop);
process.on("SIGINT", stop);
child.on("exit", (code) => {
  rmSync(temp, { recursive: true, force: true });
  process.exit(code || 0);
});
