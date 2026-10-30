window.STATE =
{
  "version": 2,
  "slug": "home-polish",
  "dir": "2026-10-30-home-polish--wip",
  "title": "CatPart — компактный каталог и графика",
  "mode": "semi",
  "depth": "normal",
  "tier": "T0",
  "briefFile": "2026-10-30-brief.md",
  "memoryFile": "AGENTS.md",
  "memoryOwner": "autopilot",
  "skillDir": "C:/Users/elena/.codex/skills/autopilot",
  "baseCommit": null,
  "startedAt": "2026-10-30T12:52:48+03:00",
  "updatedAt": "2026-10-30T13:14:35+03:00",
  "finishedAt": null,
  "stages": [
    {
      "id": "preflight",
      "status": "done",
      "startedAt": "2026-10-30T12:52:48+03:00",
      "finishedAt": "2026-10-30T12:53:23+03:00"
    },
    {
      "id": "manifest",
      "status": "done",
      "startedAt": "2026-10-30T12:53:23+03:00",
      "finishedAt": "2026-10-30T12:53:39+03:00"
    },
    {
      "id": "briefing",
      "status": "done",
      "startedAt": "2026-10-30T12:53:39+03:00",
      "finishedAt": "2026-10-30T13:13:30+03:00"
    },
    {
      "id": "spec",
      "status": "done",
      "startedAt": "2026-10-30T13:13:30+03:00",
      "finishedAt": "2026-10-30T13:14:09+03:00"
    },
    {
      "id": "plan",
      "status": "active",
      "startedAt": "2026-10-30T13:14:09+03:00"
    },
    {
      "id": "build",
      "status": "pending"
    },
    {
      "id": "review",
      "status": "pending"
    },
    {
      "id": "final",
      "status": "pending"
    }
  ],
  "requirements": {
    "total": 14,
    "done": 0,
    "inTicket": 14,
    "inSpec": 0,
    "placeholder": 0,
    "deferred": 0,
    "dropped": 0
  },
  "tickets": [
    {
      "id": "01",
      "title": "CatPart: компактная главная, графика и брендбук",
      "requirements": [
        "R01",
        "R02",
        "R03",
        "R04",
        "R05",
        "R06",
        "R07",
        "R08",
        "R09",
        "G01",
        "G02",
        "G03",
        "G04",
        "G05"
      ],
      "blockedBy": [],
      "wave": 1,
      "zone": [
        "local-dev/site/",
        "deliverables/brandbook/",
        "tools/check-site.py"
      ],
      "review": "нет",
      "model": "обычная",
      "status": "pending",
      "retries": 0,
      "repairs": 0
    }
  ],
  "tests": null,
  "debt": {
    "placeholders": [],
    "assumptions": [],
    "emptyEnv": []
  },
  "additions": [],
  "coverage": {
    "found": 1,
    "fixed": 1,
    "deferred": 0,
    "items": []
  },
  "concerns": [],
  "blind": null,
  "beats": [
    "2026-10-30T12:52:48+03:00",
    "2026-10-30T12:53:23+03:00",
    "2026-10-30T12:53:38+03:00",
    "2026-10-30T12:53:39+03:00",
    "2026-10-30T12:54:22+03:00",
    "2026-10-30T13:04:39+03:00",
    "2026-10-30T13:13:30+03:00",
    "2026-10-30T13:14:09+03:00",
    "2026-10-30T13:14:10+03:00",
    "2026-10-30T13:14:11+03:00",
    "2026-10-30T13:14:12+03:00",
    "2026-10-30T13:14:34+03:00",
    "2026-10-30T13:14:35+03:00"
  ],
  "rev": 15,
  "waiting": null,
  "waits": [
    {
      "kind": "briefing",
      "from": "2026-10-30T12:54:22+03:00",
      "to": "2026-10-30T13:13:30+03:00"
    }
  ],
  "log": [
    {
      "t": "2026-10-30T12:52:48+03:00",
      "k": "init"
    },
    {
      "t": "2026-10-30T12:53:23+03:00",
      "k": "stage",
      "id": "manifest",
      "act": "start"
    },
    {
      "t": "2026-10-30T12:53:39+03:00",
      "k": "stage",
      "id": "briefing",
      "act": "start"
    },
    {
      "t": "2026-10-30T12:54:22+03:00",
      "k": "ask",
      "kind": "briefing",
      "note": "Стиль графики и факты для текста о компании"
    },
    {
      "t": "2026-10-30T13:04:39+03:00",
      "k": "ask",
      "kind": "briefing",
      "note": "Два визуальных варианта; связь CatPart и СМ ТЕХНО, актуальность телефона"
    },
    {
      "t": "2026-10-30T13:13:30+03:00",
      "k": "answered",
      "kind": "briefing"
    },
    {
      "t": "2026-10-30T13:13:30+03:00",
      "k": "gate",
      "g": "G1",
      "ok": true,
      "open": 0,
      "total": 14
    },
    {
      "t": "2026-10-30T13:13:30+03:00",
      "k": "stage",
      "id": "spec",
      "act": "start"
    },
    {
      "t": "2026-10-30T13:14:09+03:00",
      "k": "stage",
      "id": "plan",
      "act": "start"
    },
    {
      "t": "2026-10-30T13:14:10+03:00",
      "k": "tier",
      "v": "T0"
    },
    {
      "t": "2026-10-30T13:14:11+03:00",
      "k": "tickets",
      "n": 1
    },
    {
      "t": "2026-10-30T13:14:12+03:00",
      "k": "gate",
      "g": "G3",
      "ok": false,
      "problems": 1
    },
    {
      "t": "2026-10-30T13:14:34+03:00",
      "k": "gate",
      "g": "G2",
      "ok": true,
      "found": 1,
      "fixed": 1,
      "deferred": 0
    },
    {
      "t": "2026-10-30T13:14:35+03:00",
      "k": "tickets",
      "n": 1
    },
    {
      "t": "2026-10-30T13:14:35+03:00",
      "k": "gate",
      "g": "G3",
      "ok": true,
      "problems": 0
    }
  ],
  "reqList": [
    {
      "id": "R01",
      "text": "«сделаем меньше сами плашки»",
      "status": "in-ticket"
    },
    {
      "id": "R02",
      "text": "«чтоб он занимал примерно половину из того что он сейчас занимает»",
      "status": "in-ticket"
    },
    {
      "id": "R03",
      "text": "«используя imagegen сделай для каждого раздела красивую графическую иконку»",
      "status": "in-ticket"
    },
    {
      "id": "R04",
      "text": "«написать понятное описание в разделе о компании»",
      "status": "in-ticket"
    },
    {
      "id": "R05",
      "text": "«без технических комментариев»",
      "status": "in-ticket"
    },
    {
      "id": "R06",
      "text": "«Почему у нас покупают запчасти тоже не красивый, добавить графичности в том же стиле как и в разделы каталога»",
      "status": "in-ticket"
    },
    {
      "id": "R07",
      "text": "«Каждая статья должна быть с соответствующей картинкой по смылу, тоже генерируй»",
      "status": "in-ticket"
    },
    {
      "id": "R08",
      "text": "«все картинки должны быть оптимизированы для веб, в формате wepb»",
      "status": "in-ticket"
    },
    {
      "id": "R09",
      "text": "«в карточках товаров не оцентрованы картинки, надо гармонично расположить»",
      "status": "in-ticket"
    },
    {
      "id": "G01",
      "text": "«Сделай два варианта, выберем что лучше»",
      "status": "in-ticket"
    },
    {
      "id": "G02",
      "text": "«СМ ТЕХНО — поставщик запчастей для спецтехники и сельхозтехники»",
      "status": "in-ticket"
    },
    {
      "id": "G03",
      "text": "«+7 911 921-22-14, +7 965 785-57-85, +7 965 818-56-87 и еще эти добавь»",
      "status": "in-ticket"
    },
    {
      "id": "G04",
      "text": "«по иконкам вариант Б»",
      "status": "in-ticket"
    },
    {
      "id": "G05",
      "text": "«сделай брендбук и добавь описание их производства туда»",
      "status": "in-ticket"
    }
  ],
  "gates": {
    "G1": {
      "ok": true,
      "at": "2026-10-30T13:13:30+03:00",
      "open": 0,
      "total": 14
    },
    "G3": {
      "ok": true,
      "at": "2026-10-30T13:14:35+03:00",
      "problems": 0
    },
    "G2": {
      "ok": true,
      "at": "2026-10-30T13:14:34+03:00",
      "found": 1,
      "fixed": 1,
      "deferred": 0
    }
  },
  "result": null
}
