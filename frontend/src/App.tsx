import { FormEvent, MouseEvent, ReactNode, useCallback, useEffect, useRef, useState } from "react";
import {
  ArrowLeft, ArrowRight, ArrowSquareOut, CaretDown, Check, Copy, DownloadSimple,
  House, LockKey, MagnifyingGlass, Plus, QrCode as QrIcon, SignOut, Trash, User, UsersThree, WarningCircle, X,
} from "@phosphor-icons/react";
import { toast } from "sonner";
import { api, download, Session, setCsrfToken } from "./api";
import { normalizeCode, safeNextPath } from "./logic";
import type { Batch, BatchPage, QrCode } from "./types";
import Dock from "./Dock";
import UsersPage from "./UsersPage";
import PixelBlast from "./PixelBlast";

function currentLocation() { return window.location.pathname + window.location.search; }

function useLocation() {
  const [location, setLocation] = useState(currentLocation);
  useEffect(() => {
    const update = () => setLocation(currentLocation());
    window.addEventListener("popstate", update);
    return () => window.removeEventListener("popstate", update);
  }, []);
  return location;
}

function navigate(to: string, replace = false) {
  window.history[replace ? "replaceState" : "pushState"]({}, "", to);
  window.dispatchEvent(new PopStateEvent("popstate"));
  window.scrollTo({ top: 0, behavior: "instant" });
}

function Link({ href, children, className = "", ...props }: { href: string; children: ReactNode; className?: string; [key: string]: unknown }) {
  function click(event: MouseEvent<HTMLAnchorElement>) {
    if (!event.defaultPrevented && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey) {
      event.preventDefault(); navigate(href);
    }
  }
  return <a href={href} onClick={click} className={className} {...props}>{children}</a>;
}

function formatDate(value: string) {
  const iso = value.includes("T") ? value : `${value.replace(" ", "T")}Z`;
  return new Intl.DateTimeFormat("pt-BR", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" }).format(new Date(iso));
}

function useReload() {
  const [version, setVersion] = useState(0);
  return [version, () => setVersion((value) => value + 1)] as const;
}

function AppShell({ children, username, role, onLogout }: { children: ReactNode; username: string; role: Session["role"]; onLogout: () => Promise<void> }) {
  return <div className="min-h-[100dvh]">
    <header className="border-b border-[#111] bg-[var(--header)]">
      <div className="mx-auto flex h-[76px] max-w-[1920px] items-center justify-between px-4 sm:h-[88px] sm:px-[3.1vw] 2xl:h-[122px]">
        <Link href="/" className="flex items-center gap-2 rounded-sm" aria-label="Voltar para a lista de QR Codes">
          <span className="flex items-center gap-2 text-[1.35rem] font-black leading-none tracking-[-0.03em] text-[#e7e7e7] 2xl:text-[1.75rem]"><span>QR Control</span><span className="font-black text-[0.78rem] leading-none tracking-[-0.03em] text-[#888] 2xl:text-[1rem]">@{username}</span></span>
        </Link>

      </div>
    </header>
    <main className="app-main mx-auto max-w-[1920px] px-4 py-6 pb-32 sm:px-[3.1vw] sm:py-7 sm:pb-28 2xl:py-10 2xl:pb-32">{children}</main>
    <Dock items={[...(role!=="manutencao" ? [{ icon:<House size={22}/>, label:"QR Codes", onClick:()=>navigate("/") }] : []), ...(role==="admin" || role==="manutencao" ? [{ icon:<UsersThree size={22}/>, label:"Usuários", onClick:()=>navigate("/users") }] : []), { icon:<SignOut size={22}/>, label:"Sair", onClick:onLogout }]} />
  </div>;
}

function LoginPage({ onLogin }: { onLogin: (session: Session) => void }) {
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const next = new URLSearchParams(window.location.search).get("next");
  const safeNext = safeNextPath(next);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setLoading(true); setError("");
    const form = new FormData(event.currentTarget);
    try {
      const session = await api<Session>("/api/auth/login", { method: "POST", body: JSON.stringify({ username: form.get("username"), password: form.get("password") }) });
      setCsrfToken(session.csrfToken); onLogin(session); navigate(safeNext, true);
    } catch (reason) { setError(reason instanceof Error ? reason.message : "Não foi possível entrar."); }
    finally { setLoading(false); }
  }

  return <main className="login-page relative isolate grid min-h-[100dvh] place-items-center overflow-hidden px-4 py-8 sm:py-10">
    <PixelBlast className="pointer-events-auto absolute inset-0 z-0" variant="circle" pixelSize={5} color="#ff4d08" patternScale={3} patternDensity={1.15} pixelSizeJitter={0.45} enableRipples rippleSpeed={0.4} rippleThickness={0.12} rippleIntensityScale={1.5} speed={0.6} edgeFade={0.25} transparent />
    <section className="login-card relative z-10 w-full max-w-[440px] rounded-[28px] border-2 border-[#484848] bg-[var(--surface)] p-6 text-[var(--ink-dark)] sm:p-8">
      <div className="flex items-center"><span className="text-2xl font-black tracking-[-0.03em] text-[var(--ink-dark)]">QR Control</span></div>
      <h1 className="mt-7 text-[1.75rem] font-black uppercase leading-tight tracking-[-0.03em] text-[var(--ink-dark)] sm:text-3xl">Acesso privado</h1>
      <p className="mt-2 text-sm font-medium text-[var(--ink-muted-dark)]">Entre para gerenciar seus QR Codes.</p>
      <form onSubmit={submit} className="mt-7 space-y-5 sm:mt-8" noValidate>
        <label className="block space-y-2"><span className="text-sm font-bold text-[var(--ink-dark)]">Usuário</span><span className="relative block"><User aria-hidden="true" className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[#4b4b4b]" size={20}/><input name="username" autoComplete="username" required className="login-input min-h-12 w-full rounded-[16px] border-2 border-[#707070] bg-white pl-11 pr-3 text-black" /></span></label>
        <label className="block space-y-2"><span className="text-sm font-bold text-[var(--ink-dark)]">Senha</span><span className="relative block"><LockKey aria-hidden="true" className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[#4b4b4b]" size={20}/><input name="password" type="password" autoComplete="current-password" required className="login-input min-h-12 w-full rounded-[16px] border-2 border-[#707070] bg-white pl-11 pr-3 text-black" /></span></label>
        {error && <p role="alert" className="rounded-[16px] bg-[#f5c9c6] px-3 py-2.5 text-sm font-bold text-[#8f1f18]">{error}</p>}
        <button disabled={loading} className="action-label pressable flex min-h-14 w-full items-center justify-center gap-2 rounded-full bg-[var(--accent)] px-4 font-black uppercase text-black disabled:opacity-60">{loading ? "Entrando..." : "Entrar"}<ArrowRight aria-hidden="true" size={19} weight="bold" /></button>
      </form>
    </section>
  </main>;
}

function CreateBatch({ onCreated }: { onCreated: (id: string) => void }) {
  const dialog = useRef<HTMLDialogElement>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); const formElement = event.currentTarget; setLoading(true); setError("");
    const form = new FormData(formElement);
    try {
      const result = await api<{id:string;startCode:string;endCode:string;quantity:number}>("/api/batches", { method: "POST", body: JSON.stringify({ quantity: form.get("quantity") }) });
      dialog.current?.close(); formElement.reset(); toast.success(`Lote ${result.startCode}-${result.endCode} criado com ${result.quantity} códigos.`); onCreated(result.id);
    } catch (reason) { setError(reason instanceof Error ? reason.message : "Não foi possível criar o lote."); }
    finally { setLoading(false); }
  }
  return <>
    <button onClick={() => dialog.current?.showModal()} className="action-label pressable inline-flex min-h-[52px] w-full items-center justify-center gap-2 rounded-full bg-[var(--accent)] px-7 text-lg font-black uppercase text-black lg:w-auto lg:min-w-[220px] 2xl:min-h-[70px] 2xl:min-w-[260px] 2xl:text-[1.55rem]"><Plus size={22} weight="bold"/> Novo lote</button>
    <dialog ref={dialog} onClick={(e) => e.target === dialog.current && dialog.current.close()} className="m-auto w-[calc(100%-2rem)] max-w-lg rounded-[16px] border-2 border-[#505050] bg-[#dedede] p-0 text-black shadow-2xl">
      <div className="flex items-start justify-between border-b-2 border-[#bdbdbd] px-5 py-4 sm:px-6"><div><h2 className="text-2xl font-black uppercase">Criar novo lote</h2><p className="mt-1 text-sm text-[#4f4f4f]">Os códigos serão criados aguardando o primeiro cadastro.</p></div><button onClick={() => dialog.current?.close()} aria-label="Fechar" className="grid size-11 place-items-center"><X size={20}/></button></div>
      <form onSubmit={submit} className="space-y-5 p-5 sm:p-6"><label className="block space-y-2"><span className="text-sm font-semibold">Quantidade</span><input name="quantity" type="number" min="1" max="500" placeholder="Ex.: 10" required className="min-h-12 w-full rounded-[16px] border-2 border-[#777] bg-white px-4 text-black"/><span className="block text-sm text-[#4f4f4f]">De 1 a 500 QR Codes por lote.</span></label><p className="rounded-[16px] border-2 border-[#b8b8b8] bg-[#eee] px-4 py-3 text-sm text-[#4f4f4f]">Ao escanear cada QR Code pela primeira vez, você poderá cadastrar o destino específico dele.</p>{error && <p className="rounded-[16px] bg-[#f5c9c6] px-3 py-2 text-sm font-bold text-[#8f1f18]">{error}</p>}<div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button type="button" onClick={() => dialog.current?.close()} className="action-label min-h-12 rounded-full border-2 border-[#777] px-5 font-black uppercase">Cancelar</button><button disabled={loading} className="action-label min-h-12 rounded-full bg-[var(--accent)] px-6 font-black uppercase disabled:opacity-60">{loading ? "Criando lote..." : "Criar lote"}</button></div></form>
    </dialog>
  </>;
}

function QrSearch() {
  const [query, setQuery] = useState("");
  const [items, setItems] = useState<QrCode[]>([]);
  const [open, setOpen] = useState(false);
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);
  useEffect(() => {
    if (!/^[A-Za-z0-9-]+$/.test(query.trim())) { setItems([]); setOpen(false); return; }
    const controller = new AbortController();
    const timer = window.setTimeout(() => api<{items:QrCode[]}>(`/api/qrcodes/suggestions?q=${encodeURIComponent(query)}`, { signal: controller.signal }).then((body) => { setItems(body.items); setOpen(true); setMessage(body.items.length ? "" : "Nenhum QR Code encontrado com esse número."); }).catch((e) => e.name !== "AbortError" && setMessage("Não foi possível pesquisar agora.")), 180);
    return () => { clearTimeout(timer); controller.abort(); };
  }, [query]);
  async function submit(event: FormEvent) {
    event.preventDefault(); const normalized = normalizeCode(query);
    if (!normalized) { setMessage("Digite um código de QR Code válido."); return; }
    setLoading(true);
    try { const body = await api<{items:QrCode[]}>(`/api/qrcodes/suggestions?q=${encodeURIComponent(query)}`); const exact = body.items.find((item) => item.code === normalized); exact ? navigate(`/codes/${exact.code}`) : (setItems(body.items), setOpen(true), setMessage("Esse QR Code não existe. Escolha uma sugestão.")); }
    catch (reason) { setMessage(reason instanceof Error ? reason.message : "Não foi possível pesquisar."); } finally { setLoading(false); }
  }
  return <div className="relative mt-5 max-w-[940px] sm:mt-6 2xl:mt-8" onBlur={(e) => !e.currentTarget.contains(e.relatedTarget) && setOpen(false)}>
    <form onSubmit={submit} role="search" className="flex flex-row gap-2 sm:gap-5"><div className="relative flex-1"><MagnifyingGlass size={22} weight="bold" className="absolute left-4 top-1/2 -translate-y-1/2 text-[#c8c8c8]"/><input value={query} onChange={(e) => {setQuery(e.target.value);setMessage("")}} onFocus={() => items.length && setOpen(true)} autoComplete="off" placeholder="Digite o código do QR Code" className="min-h-[52px] w-full rounded-full border-2 border-[#c8c8c8] bg-transparent pl-12 pr-4 text-base font-semibold text-white placeholder:text-[#bdbdbd] sm:text-lg 2xl:min-h-[61px]"/>{open && <div role="listbox" className="app-scrollbar absolute left-0 right-0 top-[calc(100%+.5rem)] z-30 max-h-[min(380px,55dvh)] overflow-y-auto rounded-[18px] border-2 border-[#686868] bg-[#f4f4f4] p-1.5 text-[#101010] shadow-2xl">{items.length ? items.map((item, index) => <button key={item.code} type="button" onMouseDown={(e) => e.preventDefault()} onClick={() => navigate(`/codes/${item.code}`)} className={`flex min-h-14 w-full items-center gap-3 rounded-[16px] px-3 text-left ${index === 0 ? "bg-[#ffd9ca] ring-1 ring-[#ff9a72]" : "hover:bg-[#dfdfdf]"}`}><span className="grid size-9 place-items-center rounded-[10px] bg-[#252525] text-white"><QrIcon size={20}/></span><span className="min-w-0 flex-1"><span className="flex gap-2"><b className="text-[#b83200]">{item.code}</b><Status qr={item}/></span><span className="mt-0.5 block truncate text-sm font-medium text-[#303030]">{item.destinationUrl ?? "Aguardando primeiro cadastro"}</span></span><ArrowRight size={18}/></button>) : <p className="px-3 py-3 text-sm">Nenhuma sugestão encontrada.</p>}</div>}</div><button disabled={loading} className="action-label min-h-[52px] shrink-0 rounded-full bg-[#cecece] px-5 text-base font-black uppercase text-black sm:px-7 sm:text-lg 2xl:min-h-[61px]">{loading ? "Buscando..." : "Buscar"}</button></form>
    <p aria-live="polite" className={`mt-2 min-h-5 text-sm ${message ? "text-[var(--danger)]" : "sr-only"}`}>{message}</p>
  </div>;
}

function Status({ qr }: { qr: Pick<QrCode, "destinationUrl" | "active"> }) {
  return <span className={`rounded-full px-2 py-0.5 text-xs font-bold ${!qr.destinationUrl ? "bg-[#ffd9ca] text-[#8d2905]" : qr.active ? "bg-[#cdebdc] text-[#075b36]" : "bg-[#d4d4d4] text-[#242424]"}`}>{!qr.destinationUrl ? "Sem cadastro" : qr.active ? "Ativo" : "Inativo"}</span>;
}

function DownloadBatchButton({ id }: { id: string }) {
  const [loading, setLoading] = useState(false);
  async function run() { setLoading(true); try { await download(`/api/batches/${id}/export`); toast.success("ZIP gerado e pronto para produção."); } catch (e) { toast.error(e instanceof Error ? e.message : "Falha no download."); } finally { setLoading(false); } }
  return <button onClick={run} disabled={loading} className="action-label inline-flex min-h-[50px] items-center justify-center gap-2 rounded-full bg-[var(--accent)] px-5 font-black uppercase text-black disabled:opacity-60"><DownloadSimple size={20} weight="bold"/>{loading ? "Gerando..." : "Baixar ZIP"}</button>;
}

function BatchList({ batches, initiallyOpen, onChanged }: { batches: Batch[]; initiallyOpen?: string; onChanged: () => void }) {
  const [openIds, setOpenIds] = useState(new Set(initiallyOpen ? [initiallyOpen] : []));
  const [selected, setSelected] = useState(new Set<string>());
  const [busy, setBusy] = useState(false);
  const dialog = useRef<HTMLDialogElement>(null);
  const selectedBatches = batches.filter((b) => selected.has(b.id));
  const codeCount = selectedBatches.reduce((total, b) => total + b.quantity, 0);
  const all = batches.length > 0 && batches.every((b) => selected.has(b.id));
  useEffect(() => { if (initiallyOpen) setOpenIds(new Set([initiallyOpen])); }, [initiallyOpen]);
  function toggle(setter: typeof setSelected, id: string) { setter((current) => { const next = new Set(current); next.has(id) ? next.delete(id) : next.add(id); return next; }); }
  async function exportSelected() { setBusy(true); try { await download('/api/batches/export', { method:'POST', body:JSON.stringify({batchIds:[...selected]}) }); toast.success(`${selected.size} lote(s) reunido(s) em um ZIP.`); } catch(e) { toast.error(e instanceof Error ? e.message : 'Falha no download.'); } finally { setBusy(false); } }
  async function deleteSelected() { setBusy(true); try { const result = await api<{batchCount:number;qrCodeCount:number}>('/api/batches/delete',{method:'DELETE',body:JSON.stringify({batchIds:[...selected]})}); dialog.current?.close(); setSelected(new Set()); toast.success(`${result.batchCount} lote(s) e ${result.qrCodeCount} QR Code(s) excluído(s).`); navigate('/'); onChanged(); } catch(e) { toast.error(e instanceof Error ? e.message : 'Falha ao excluir.'); } finally { setBusy(false); } }
  return <section className="mt-7 sm:mt-8 2xl:mt-[70px]">
    <div className="mb-4 flex flex-col gap-3 rounded-[22px] border-[3px] border-[var(--line)] bg-[var(--surface-dark)] px-4 py-3 lg:flex-row lg:items-center lg:justify-between 2xl:mb-9"><label className="flex min-h-11 cursor-pointer items-center gap-3 text-base font-bold text-[#cecece] sm:text-lg"><input type="checkbox" checked={all} onChange={() => setSelected(all ? new Set() : new Set(batches.map((b) => b.id)))} className="ui-checkbox"/>Selecionar todos os lotes</label><div className="flex flex-col gap-3 lg:flex-row lg:items-center"><p className="text-base font-bold text-[#c7c7c7]">{selected.size ? `${selected.size} lote(s) · ${codeCount} QR Codes` : "Nenhum lote selecionado"}</p><div className="flex flex-col gap-2 sm:flex-row"><button disabled={!selected.size || busy} onClick={exportSelected} className="action-label inline-flex min-h-[50px] items-center justify-center gap-2 rounded-full bg-[var(--accent)] px-6 font-black uppercase text-black disabled:brightness-75"><DownloadSimple size={20}/>Baixar selecionados</button><button disabled={!selected.size || busy} onClick={() => dialog.current?.showModal()} className="action-label inline-flex min-h-[50px] items-center justify-center gap-2 rounded-full bg-[#d9362b] px-6 font-black uppercase text-white disabled:brightness-75"><Trash size={20}/>Excluir</button></div></div></div>
    <dialog ref={dialog} className="m-auto w-[calc(100%-2rem)] max-w-[560px] rounded-[16px] border-2 border-[#5c5c5c] bg-[#dedede] p-0 text-black shadow-2xl"><div className="flex items-start justify-between border-b-2 border-[#b9b9b9] p-5"><div className="flex gap-3"><span className="grid size-11 place-items-center rounded-full bg-[#f5c9c6] text-[#a91f17]"><WarningCircle size={25} weight="fill"/></span><div><h2 className="text-xl font-black uppercase">Excluir lotes selecionados?</h2><p className="text-sm text-[#4f4f4f]">Esta ação é definitiva e os QR Codes deixarão de funcionar.</p></div></div><button onClick={() => dialog.current?.close()}><X size={20}/></button></div><div className="p-5"><p className="rounded-[16px] border-2 border-[#b7b7b7] bg-[#eee] px-4 py-3 font-black">{selected.size} lote(s) · {codeCount} QR Code(s)</p><ul className="mt-4 max-h-52 space-y-2 overflow-auto">{selectedBatches.map((b) => <li key={b.id} className="flex justify-between rounded-[14px] bg-[#cfcfcf] px-4 py-3"><b>Lote {b.startCode} - {b.endCode}</b><span>{b.quantity} QR Codes</span></li>)}</ul><p className="mt-4 text-sm font-bold text-[#8f1f18]">Os números excluídos não serão reaproveitados.</p><div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button onClick={() => dialog.current?.close()} className="action-label min-h-12 rounded-full border-2 border-[#777] px-5 font-black uppercase">Cancelar</button><button disabled={busy} onClick={deleteSelected} className="action-label min-h-12 rounded-full bg-[#d9362b] px-6 font-black uppercase text-white">{busy ? "Excluindo..." : "Confirmar exclusão"}</button></div></div></dialog>
    <div className="space-y-4 2xl:space-y-9">{batches.map((batch) => { const open=openIds.has(batch.id); return <article key={batch.id} className={`overflow-hidden rounded-[22px] border-[3px] bg-[var(--surface)] text-black ${selected.has(batch.id)?'border-[var(--accent)]':'border-[#454545]'}`}><div className="flex min-h-[92px] items-stretch gap-1 px-3 py-2"><label className="grid min-h-11 min-w-11 cursor-pointer place-items-center"><input type="checkbox" checked={selected.has(batch.id)} onChange={() => toggle(setSelected,batch.id)} className="ui-checkbox"/></label><button onClick={() => toggle(setOpenIds,batch.id)} className="flex min-w-0 flex-1 items-center gap-2 rounded-[16px] px-1 text-left hover:bg-[#d2d2d2]"><span className="grid size-11 place-items-center rounded-[7px] bg-[#464646] text-white"><QrIcon size={30}/></span><span className="min-w-0 flex-1"><b className="block truncate text-lg font-black uppercase sm:text-[1.35rem]">Lote {batch.startCode} - {batch.endCode}</b><span className="text-sm font-semibold text-[#4f4f4f]">{batch.quantity} QR Codes · {formatDate(batch.createdAt)}</span></span><CaretDown size={30} className={open?'rotate-180':''}/></button><div className="hidden items-center lg:flex"><DownloadBatchButton id={batch.id}/></div></div><div className="border-t-2 border-[#bbb] p-3 lg:hidden"><DownloadBatchButton id={batch.id}/></div>{open && <div className="grid grid-cols-1 border-t-2 border-[#bbb] bg-[#ededed]">{batch.qrCodes.map((qr) => <Link key={qr.id} href={`/codes/${qr.code}`} className="flex min-w-0 items-center justify-between gap-3 border-b border-[#c7c7c7] px-4 py-3.5 hover:bg-white"><span className="min-w-0"><span className="flex gap-2"><b className="text-[#c33a08]">{qr.code}</b><Status qr={qr}/></span><span className="mt-1 block truncate text-sm text-[#4f4f4f]">{qr.destinationUrl ?? 'Aguardando primeiro cadastro'}</span></span><ArrowRight size={18}/></Link>)}</div>}</article>;})}</div>
  </section>;
}

function Dashboard() {
  const page = Math.max(1, Number(new URLSearchParams(window.location.search).get("page")) || 1);
  const initiallyOpen = new URLSearchParams(window.location.search).get("batch") ?? undefined;
  const [data, setData] = useState<BatchPage | null>(null);
  const [version, reload] = useReload();
  useEffect(() => {
    let active = true;
    let requestId = 0;
    let controller: AbortController | null = null;
    const refresh = (notifyError: boolean) => {
      controller?.abort();
      controller = new AbortController();
      const currentRequest = ++requestId;
      api<BatchPage>(`/api/batches?page=${page}&_=${Date.now()}`, {
        cache: "no-store",
        signal: controller.signal,
        headers: { "cache-control": "no-cache" },
      })
        .then((next) => {
          if (active && currentRequest === requestId) setData(next);
        })
        .catch((e) => {
          if (e?.name === "AbortError") return;
          if (active && notifyError) toast.error(e instanceof Error ? e.message : "Não foi possível atualizar os QR Codes.");
        });
    };
    const onFocus = () => refresh(false);
    const onPageShow = () => refresh(false);
    refresh(true);
    const interval = window.setInterval(() => refresh(false), 1500);
    window.addEventListener("focus", onFocus);
    window.addEventListener("pageshow", onPageShow);
    return () => {
      active = false;
      controller?.abort();
      window.clearInterval(interval);
      window.removeEventListener("focus", onFocus);
      window.removeEventListener("pageshow", onPageShow);
    };
  }, [page, version]);
  const pages = Math.max(1, Math.ceil((data?.total ?? 0) / 10));
  return <>{data ? <><div className="flex flex-col gap-5 lg:flex-row lg:justify-between"><div><h1 className="text-[1.7rem] font-black uppercase leading-none sm:text-[2rem] 2xl:text-[2.45rem]">Gerenciar QR Codes</h1><p className="mt-2 text-base font-semibold text-[var(--ink-muted)] sm:text-xl">{data.total} lote(s) gerado(s)</p></div><CreateBatch onCreated={(id) => { navigate(`/?batch=${id}`); reload(); }}/></div><QrSearch/>{data.items.length ? <BatchList batches={data.items} initiallyOpen={initiallyOpen} onChanged={reload}/> : <Empty/>}{pages>1 && <nav className="mt-5 flex items-center justify-between rounded-[22px] border-2 border-[var(--line)] bg-[var(--surface-dark)] px-4 py-3"><p>Página {page} de {pages}</p><div className="flex gap-2">{page>1&&<Link href={`/?page=${page-1}`} className="rounded-full border px-4 py-2"><ArrowLeft/></Link>}{page<pages&&<Link href={`/?page=${page+1}`} className="rounded-full border px-4 py-2"><ArrowRight/></Link>}</div></nav>}</> : <Loading/>}</>;
}

function Empty(){return <section className="mt-12 grid min-h-72 place-items-center rounded-[30px] border-2 border-[var(--line)] bg-[var(--surface)] p-6 text-center text-black"><div><QrIcon className="mx-auto" size={42}/><h2 className="mt-4 font-semibold">Nenhum lote criado</h2><p>Crie o primeiro lote para começar.</p></div></section>}
function Loading(){return <div className="grid min-h-[50dvh] place-items-center text-[var(--ink-muted)]">Carregando...</div>}

function Back(){return <Link href="/" className="inline-flex min-h-11 items-center gap-2 text-sm font-bold text-[var(--ink-muted)] hover:text-white"><ArrowLeft size={18}/> Voltar para a lista</Link>}

function BatchDetails({ id }: { id:string }) {
  const [batch,setBatch]=useState<Batch|null>(null);
  useEffect(()=>{api<Batch>(`/api/batches/${id}`).then(setBatch).catch(()=>navigate('/'))},[id]);
  if(!batch)return <Loading/>;
  return <><Back/><div className="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><h1 className="text-3xl font-black uppercase">Lote {batch.startCode}-{batch.endCode}</h1><p className="mt-1 text-sm text-[var(--ink-muted)]">{batch.quantity} arquivos PNG · criado em {formatDate(batch.createdAt)}</p></div><DownloadBatchButton id={batch.id}/></div><section className="mt-6 overflow-hidden rounded-[30px] border-[3px] border-[#454545] bg-[var(--surface)] text-black"><div className="border-b-2 border-[#bbb] px-5 py-4"><h2 className="text-xl font-black uppercase">Códigos deste lote</h2><p className="text-sm text-[#4f4f4f]">O ZIP contém uma imagem PNG por código.</p></div><div className="grid grid-cols-1">{batch.qrCodes.map(qr=><Link key={qr.id} href={`/codes/${qr.code}`} className="flex min-w-0 items-center justify-between gap-3 border-b border-[#c2c2c2] px-5 py-3.5 hover:bg-white"><span className="min-w-0"><span className="flex gap-2"><b className="text-[#c43a08]">{qr.code}</b><Status qr={qr}/></span><span className="block truncate text-sm text-[#4f4f4f]">{qr.destinationUrl??'Aguardando primeiro cadastro'}</span></span><ArrowRight/></Link>)}</div></section></>;
}

function CopyButton({value}:{value:string}){const[copied,setCopied]=useState(false);const[error,setError]=useState(false);async function copy(){setError(false);try{if(navigator.clipboard?.writeText){await navigator.clipboard.writeText(value)}else{const input=document.createElement('textarea');input.value=value;input.setAttribute('readonly','');input.style.position='fixed';input.style.opacity='0';document.body.appendChild(input);input.select();document.execCommand('copy');input.remove()}setCopied(true);window.setTimeout(()=>setCopied(false),1600)}catch{setError(true)}}return <button type="button" onClick={copy} className="action-label grid min-h-11 min-w-11 place-items-center rounded-full bg-[var(--accent)] text-black" aria-label={error?'Não foi possível copiar a URL':'Copiar URL'} title={error?'Não foi possível copiar a URL':'Copiar URL'}>{copied?<Check/>:<Copy/>}</button>}

function QrDetails({ code }: {code:string}) {
  const[qr,setQr]=useState<QrCode|null>(null);const[loading,setLoading]=useState(false);const[error,setError]=useState('');
  const load=useCallback(()=>api<QrCode>(`/api/qrcodes/${code}`).then(setQr).catch(()=>navigate('/')),[code]);useEffect(()=>{load()},[load]);
  async function save(e:FormEvent<HTMLFormElement>){e.preventDefault();setLoading(true);setError('');const f=new FormData(e.currentTarget);try{await api(`/api/qrcodes/${code}`,{method:'PUT',body:JSON.stringify({destinationUrl:f.get('destinationUrl'),active:f.get('active')==='on'})});toast.success('Alterações salvas.');load()}catch(x){setError(x instanceof Error?x.message:'Falha ao salvar.')}finally{setLoading(false)}}
  if(!qr)return <Loading/>;const dynamicUrl=`${window.location.origin}/q/${qr.code}`;
  return <><Back/><div className="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><div className="flex flex-wrap items-center gap-2.5"><h1 className="text-[1.9rem] font-black uppercase leading-none sm:text-3xl">QR {qr.code}</h1><Status qr={qr}/></div><p className="text-sm text-[var(--ink-muted)]">Criado no lote {qr.batch?.startCode}-{qr.batch?.endCode}</p></div><div className="flex gap-2"><a href={`/api/qrcodes/${qr.code}/image?format=svg`} className="action-label rounded-full bg-[var(--accent)] px-5 py-3 font-black text-black">SVG</a><a href={`/api/qrcodes/${qr.code}/image?format=png`} className="action-label rounded-full bg-[var(--accent)] px-5 py-3 font-black text-black">PNG</a></div></div><div className="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]"><section className="rounded-[22px] border-[3px] border-[#454545] bg-[var(--surface)] p-4 text-black sm:rounded-[30px] sm:p-5"><h2 className="text-xl font-black uppercase">Destino e status</h2><form onSubmit={save} className="mt-5 space-y-6"><label className="block space-y-2"><b className="text-sm">Endereço de destino</b><input name="destinationUrl" type="url" defaultValue={qr.destinationUrl??''} placeholder="https://exemplo.com" required className="min-h-12 w-full rounded-[16px] border-2 border-[#777] bg-white px-4 text-black"/><span className="block text-sm text-[#4f4f4f]">Aceita endereços HTTP ou HTTPS.</span></label><label className="flex items-center justify-between gap-3 rounded-[18px] border-2 border-[#b8b8b8] bg-[#eee] px-4 py-3"><span className="min-w-0"><b className="block text-sm">QR Code ativo</b><span className="text-sm text-[#4f4f4f]">Quando inativo, nenhum redirecionamento será feito.</span></span><input type="checkbox" name="active" defaultChecked={qr.active} className="ui-checkbox"/></label>{error&&<p className="text-[#8f1f18]">{error}</p>}<button disabled={loading} className="action-label min-h-12 w-full rounded-full bg-[var(--accent)] px-6 font-black uppercase text-black sm:w-auto">{loading?'Salvando...':'Salvar alterações'}</button></form></section><div className="space-y-6"><section className="rounded-[30px] border-[3px] border-[#454545] bg-[var(--surface)] p-5 text-black"><div className="mx-auto aspect-square max-w-[260px] rounded-[12px] bg-white p-3"><img src={`/api/qrcodes/${qr.code}/image?format=svg&inline=1`} alt={`QR Code ${qr.code}`} className="size-full"/></div><h2 className="mt-5 text-sm font-semibold">URL permanente para QR e NFC</h2><div className="mt-2 flex items-start gap-2 rounded-[18px] bg-[#c8c8c8] p-2 pl-3"><code className="url-wrap flex-1 pt-2 text-sm">{dynamicUrl}</code><CopyButton value={dynamicUrl}/></div></section><section className="rounded-[30px] border-[3px] border-[#454545] bg-[var(--surface)] p-5 text-sm text-black"><h2 className="font-semibold">Informações</h2><dl className="mt-4 grid grid-cols-[auto_1fr] gap-3 text-[#4f4f4f]"><dt>Criado</dt><dd className="text-right text-black">{formatDate(qr.createdAt!)}</dd><dt>Atualizado</dt><dd className="text-right text-black">{formatDate(qr.updatedAt!)}</dd><dt>Lote</dt><dd className="text-right"><Link className="font-bold text-[#c43a08]" href={`/batches/${qr.batchId}`}>{qr.batch?.startCode}-{qr.batch?.endCode}</Link></dd></dl></section></div></div></>;
}

function RegisterPage({code}:{code:string}){const[qr,setQr]=useState<QrCode|null>(null);const[loading,setLoading]=useState(false);const[error,setError]=useState('');useEffect(()=>{api<QrCode>(`/api/qrcodes/${code}`).then(q=>q.destinationUrl?window.location.replace(`/q/${code}`):setQr(q)).catch(()=>navigate('/'))},[code]);async function save(e:FormEvent<HTMLFormElement>){e.preventDefault();setLoading(true);setError('');const f=new FormData(e.currentTarget);try{await api(`/api/qrcodes/${code}/register`,{method:'POST',body:JSON.stringify({destinationUrl:f.get('destinationUrl')})});window.location.replace(`/q/${code}`)}catch(x){setError(x instanceof Error?x.message:'Falha no cadastro.')}finally{setLoading(false)}}if(!qr)return <Loading/>;return <div className="mx-auto max-w-[620px]"><Back/><section className="mt-4 rounded-[24px] border-[3px] border-[#454545] bg-[var(--surface)] p-5 text-black sm:p-7"><div className="flex items-center gap-4"><span className="grid size-14 place-items-center rounded-[12px] bg-[#333] text-white"><QrIcon size={32}/></span><div><p className="text-sm font-bold uppercase text-[#a1320a]">Primeiro cadastro</p><h1 className="text-2xl font-black uppercase sm:text-3xl">QR Code {code}</h1></div></div><p className="mt-5 text-base font-medium text-[#4f4f4f]">Este QR Code ainda não possui destino. Cadastre o link uma única vez; nos próximos escaneamentos ele abrirá diretamente.</p>{!qr.active&&<p className="mt-4 rounded-[16px] bg-[#fff0c9] px-4 py-3 text-sm font-bold text-[#745000]">Ao concluir, este QR Code também será reativado.</p>}<form onSubmit={save} className="mt-6 space-y-5"><label className="block space-y-2"><b className="text-sm">Link de redirecionamento</b><input name="destinationUrl" type="url" autoFocus required placeholder="https://exemplo.com" className="min-h-13 w-full rounded-[16px] border-2 border-[#777] bg-white px-4 text-black"/><span className="block text-sm text-[#4f4f4f]">Use um endereço completo iniciado por https:// ou http://.</span></label>{error&&<p className="rounded-[16px] bg-[#f5c9c6] px-4 py-3 text-sm font-bold text-[#8f1f18]">{error}</p>}<button disabled={loading} className="action-label flex min-h-13 w-full items-center justify-center gap-2 rounded-full bg-[var(--accent)] px-6 font-black uppercase text-black">{loading?'Cadastrando...':'Cadastrar e abrir destino'}<ArrowSquareOut size={20}/></button></form></section></div>}

function NotFound(){return <section className="mx-auto mt-20 max-w-md rounded-[28px] bg-[var(--surface)] p-8 text-center text-black"><h1 className="text-3xl font-black uppercase">Página não encontrada</h1><Link href="/" className="mt-6 inline-flex rounded-full bg-[var(--accent)] px-6 py-3 font-black uppercase">Voltar ao painel</Link></section>}

export default function App(){const location=useLocation();const[session,setSession]=useState<Session|null>(null);const path=window.location.pathname;useEffect(()=>{const refresh=()=>api<Session>('/api/auth/session').then(s=>{setCsrfToken(s.csrfToken);setSession(s)}).catch(()=>setSession({authenticated:false,username:null,role:null,csrfToken:''}));refresh();window.addEventListener('qr:unauthorized',refresh);return()=>window.removeEventListener('qr:unauthorized',refresh)},[]);useEffect(()=>{if(!session)return;if(path==='/login'&&session.authenticated)navigate('/',true);else if(path!=='/login'&&!session.authenticated)navigate(`/login?next=${encodeURIComponent(location)}`,true);else if(session.role==='manutencao'&&path!=='/users')navigate('/users',true);else if(session.role==='vendedor'&&path==='/users')navigate('/',true)},[location,path,session]);if(!session)return <Loading/>;if(path==='/login')return session.authenticated?null:<LoginPage onLogin={setSession}/>;if(!session.authenticated)return null;async function logout(){try{await api('/api/auth/logout',{method:'POST'});setSession({authenticated:false,username:null,role:null,csrfToken:''});navigate('/login',true)}catch(e){toast.error(e instanceof Error?e.message:'Falha ao sair.')}}let content:ReactNode;if(path==='/')content=<Dashboard/>;else if(path==='/users' && (session.role==='admin' || session.role==='manutencao'))content=<UsersPage actorRole={session.role}/>;else if(/^\/batches\/([0-9a-f-]+)$/i.test(path))content=<BatchDetails id={path.split('/')[2]}/>;else if(/^\/codes\/([A-Za-z0-9-]+)$/.test(path))content=<QrDetails code={path.split('/')[2]}/>;else if(/^\/register\/([A-Za-z0-9-]+)$/.test(path))content=<RegisterPage code={path.split('/')[2]}/>;else content=<NotFound/>;return <AppShell username={session.username ?? ""} role={session.role} onLogout={logout}>{content}</AppShell>}
