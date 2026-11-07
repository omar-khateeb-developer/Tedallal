import os

raw = os.environ.get("SSH_KEY", "").strip()
if "\\n" in raw:
    raw = raw.replace("\\n", "\n")

lines = [l.strip() for l in raw.splitlines() if l.strip()]
if len(lines) <= 1:
    tokens = raw.split()
    h_end = -1
    e_start = -1
    for i, t in enumerate(tokens):
        if t.endswith("KEY-----"):
            if h_end == -1:
                h_end = i
        if t.startswith("-----END"):
            e_start = i
    if h_end != -1 and e_start != -1:
        header = " ".join(tokens[:h_end+1])
        footer = " ".join(tokens[e_start:])
        body = "".join(tokens[h_end+1:e_start])
        chunks = [body[j:j+64] for j in range(0, len(body), 64)]
        raw = header + "\n" + "\n".join(chunks) + "\n" + footer + "\n"

path = os.path.expanduser("~/.ssh/id_rsa")
with open(path, "w") as f:
    f.write(raw.strip() + "\n")

