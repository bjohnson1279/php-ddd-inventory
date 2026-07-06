import React, { useEffect, useState, useMemo } from 'react';
import api from '../api/client';
import Spinner from '../components/Spinner';

type Variant = {
  id: string;
  sku: string;
  price: number;
  attributes: Record<string, string>;
};

type Product = {
  id: string;
  name: string;
  description: string;
  department: string;
  variants: Variant[];
};

export default function Catalog() {
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);

  // New Product fields
  const [prodName, setProdName] = useState('');
  const [prodDesc, setProdDesc] = useState('');
  const [prodDept, setProdDept] = useState('GEN');
  const [prodMsg, setProdMsg] = useState('');

  // New Variant fields
  const [selectedProdId, setSelectedProdId] = useState('');
  const [varSku, setVarSku] = useState('');
  const [varPrice, setVarPrice] = useState('');
  const [varColor, setVarColor] = useState('');
  const [varSize, setVarSize] = useState('');
  const [varMsg, setVarMsg] = useState('');

  // Barcode Assign fields
  const [selectedVarId, setSelectedVarId] = useState('');
  const [barcodeVal, setBarcodeVal] = useState('');
  const [symbology, setSymbology] = useState('ean_13');
  const [source, setSource] = useState('supplier');
  const [isPrimary, setIsPrimary] = useState(true);
  const [barcodeMsg, setBarcodeMsg] = useState('');

  // Barcode Lookup fields
  const [lookupVal, setLookupVal] = useState('');
  const [lookupResult, setLookupResult] = useState<string | null>(null);
  const [lookupError, setLookupError] = useState('');

  // Loading states
  const [isCreatingProd, setIsCreatingProd] = useState(false);
  const [isAddingVar, setIsAddingVar] = useState(false);
  const [isAssigning, setIsAssigning] = useState(false);
  const [isResolving, setIsResolving] = useState(false);

  useEffect(() => {
    fetchProducts();
  }, []);

  const fetchProducts = async () => {
    try {
      setLoading(true);
      const res = await api.get('/catalog/products');
      setProducts(res.products || []);
      if (res.products && res.products.length > 0 && !selectedProdId) {
        setSelectedProdId(res.products[0].id);
      }
    } catch (e: any) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const createProduct = async (e: React.FormEvent) => {
    e.preventDefault();
    setProdMsg('');
    setIsCreatingProd(true);
    try {
      await api.post('/catalog/products', {
        name: prodName,
        description: prodDesc,
        department: prodDept
      });
      setProdMsg('Product created successfully!');
      setProdName('');
      setProdDesc('');
      fetchProducts();
    } catch (err: any) {
      setProdMsg(err.message || 'Error creating product');
    } finally {
      setIsCreatingProd(false);
    }
  };

  const createVariant = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedProdId) {
      setVarMsg('Select a product first.');
      return;
    }
    setVarMsg('');
    setIsAddingVar(true);
    try {
      const attributes: Record<string, string> = {};
      if (varColor) attributes['color'] = varColor;
      if (varSize) attributes['size'] = varSize;

      await api.post(`/catalog/products/${selectedProdId}/variants`, {
        sku: varSku,
        attributes,
        price: parseFloat(varPrice) || 0
      });
      setVarMsg('Variant added successfully!');
      setVarSku('');
      setVarPrice('');
      setVarColor('');
      setVarSize('');
      fetchProducts();
    } catch (err: any) {
      setVarMsg(err.message || 'Error adding variant');
    } finally {
      setIsAddingVar(false);
    }
  };

  const assignBarcode = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedVarId) {
      setBarcodeMsg('Select a variant first.');
      return;
    }
    setBarcodeMsg('');
    setIsAssigning(true);
    try {
      await api.post('/barcodes/assign', {
        variant_id: selectedVarId,
        value: barcodeVal,
        symbology,
        source,
        is_primary: isPrimary
      });
      setBarcodeMsg('Barcode assigned successfully!');
      setBarcodeVal('');
      fetchProducts();
    } catch (err: any) {
      setBarcodeMsg(err.message || 'Error assigning barcode');
    } finally {
      setIsAssigning(false);
    }
  };

  const lookupBarcode = async (e: React.FormEvent) => {
    e.preventDefault();
    setLookupResult(null);
    setLookupError('');
    setIsResolving(true);
    try {
      const res = await api.get(`/barcodes/lookup?value=${lookupVal}`);
      if (res.variant_id) {
        setLookupResult(res.variant_id);
      } else {
        setLookupError('Not found');
      }
    } catch (err: any) {
      setLookupError(err.message || 'Barcode not found');
    } finally {
      setIsResolving(false);
    }
  };

  // Bolt Optimization: Memoize the flat variant map computation
  // Expected Impact: Prevents O(N*M) array allocations on every keystroke
  // when editing forms, reducing unnecessary React re-renders.
  const allVariants = useMemo(() => products.flatMap(p =>
    p.variants.map(v => ({
      ...v,
      productName: p.name
    }))
  ), [products]);

  useEffect(() => {
    if (allVariants.length > 0 && !selectedVarId) {
      setSelectedVarId(allVariants[0].id);
    }
  }, [products]);

  return (
    <div>
      <h2 style={{ marginBottom: '1.5rem', fontWeight: 600 }}>Catalog & Products</h2>

      <div className="grid-2">
        {/* Left Column: List Products and Variants */}
        <div>
          <div className="section-title">Product Catalog</div>
          {loading ? (
            <div className="text-muted">Loading catalog...</div>
          ) : products.length === 0 ? (
            <div className="text-muted">No products in catalog yet.</div>
          ) : (
            products.map((prod) => (
              <div className="card-lite" key={prod.id} style={{ marginBottom: '1rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                  <div>
                    <h3 style={{ fontSize: '1.1rem', color: '#fff' }}>{prod.name}</h3>
                    <span className="text-muted" style={{ fontSize: '0.8rem' }}>Dept: {prod.department} | ID: {prod.id}</span>
                    <p style={{ textAlign: 'left', color: '#9ca3af', marginTop: '0.25rem', fontSize: '0.875rem' }}>{prod.description}</p>
                  </div>
                </div>

                {prod.variants.length > 0 && (
                  <table style={{ fontSize: '0.85rem', marginTop: '0.75rem', marginBottom: 0 }}>
                    <thead>
                      <tr>
                        <th>SKU</th>
                        <th>Price</th>
                        <th>Attributes</th>
                        <th>Variant ID</th>
                      </tr>
                    </thead>
                    <tbody>
                      {prod.variants.map((v) => (
                        <tr key={v.id}>
                          <td style={{ fontWeight: 600, color: '#818cf8' }}>{v.sku}</td>
                          <td>${v.price.toFixed(2)}</td>
                          <td>
                            {Object.entries(v.attributes).map(([key, val]) => (
                              <span key={key} className="badge badge-pending" style={{ marginRight: '0.25rem', textTransform: 'none' }}>
                                {key}: {val}
                              </span>
                            ))}
                          </td>
                          <td className="text-muted">{v.id}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            ))
          )}
        </div>

        {/* Right Column: Forms */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          {/* Create Product Form */}
          <div className="card-lite">
            <div className="section-title">Create Product</div>
            <form onSubmit={createProduct}>
              <div className="form-group">
                <label htmlFor="prodName">Product Name</label>
                <input id="prodName" value={prodName} onChange={e => setProdName(e.target.value)} placeholder="e.g. Classic Denim Jacket" required />
              </div>
              <div className="form-group">
                <label htmlFor="prodDesc">Description</label>
                <input id="prodDesc" value={prodDesc} onChange={e => setProdDesc(e.target.value)} placeholder="e.g. 100% Cotton raw denim outerwear" required />
              </div>
              <div className="form-group">
                <label htmlFor="prodDept">Department</label>
                <select id="prodDept" value={prodDept} onChange={e => setProdDept(e.target.value)}>
                  <option value="GEN">General</option>
                  <option value="APP">Apparel</option>
                  <option value="FTW">Footwear</option>
                  <option value="ACC">Accessories</option>
                </select>
              </div>
              <button type="submit" className="btn-primary" style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem' }} disabled={isCreatingProd} aria-busy={isCreatingProd}>
                {isCreatingProd && <Spinner />} {isCreatingProd ? 'Creating...' : 'Create Product'}
              </button>
            </form>
            {prodMsg && (
              <p role="alert" style={{ color: prodMsg === 'Product created successfully!' ? '#34d399' : '#f87171' }}>
                {prodMsg}
              </p>
            )}
          </div>

          {/* Add Variant Form */}
          <div className="card-lite">
            <div className="section-title">Add Variant</div>
            <form onSubmit={createVariant}>
              <div className="form-group">
                <label htmlFor="selectedProdId">Parent Product</label>
                <select id="selectedProdId" value={selectedProdId} onChange={e => setSelectedProdId(e.target.value)}>
                  {products.map(p => (
                    <option key={p.id} value={p.id}>{p.name}</option>
                  ))}
                </select>
              </div>
              <div className="form-group">
                <label htmlFor="varSku">SKU</label>
                <input id="varSku" value={varSku} onChange={e => setVarSku(e.target.value)} placeholder="e.g. DNM-JKT-BLU-M" required />
              </div>
              <div className="form-group">
                <label htmlFor="varPrice">Price</label>
                <input id="varPrice" type="number" step="0.01" value={varPrice} onChange={e => setVarPrice(e.target.value)} placeholder="e.g. 89.99" required />
              </div>
              <div className="grid-2">
                <div className="form-group">
                  <label htmlFor="varColor">Color Attribute</label>
                  <input id="varColor" value={varColor} onChange={e => setVarColor(e.target.value)} placeholder="e.g. Blue" />
                </div>
                <div className="form-group">
                  <label htmlFor="varSize">Size Attribute</label>
                  <input id="varSize" value={varSize} onChange={e => setVarSize(e.target.value)} placeholder="e.g. Medium" />
                </div>
              </div>
              <button type="submit" className="btn-primary" style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem' }} disabled={isAddingVar} aria-busy={isAddingVar}>
                {isAddingVar && <Spinner />} {isAddingVar ? 'Adding...' : 'Add Variant'}
              </button>
            </form>
            {varMsg && (
              <p role="alert" style={{ color: varMsg === 'Variant added successfully!' ? '#34d399' : '#f87171' }}>
                {varMsg}
              </p>
            )}
          </div>

          {/* Barcode Assignment Form */}
          <div className="card-lite">
            <div className="section-title">Assign Barcode</div>
            <form onSubmit={assignBarcode}>
              <div className="form-group">
                <label htmlFor="selectedVarId">Variant</label>
                <select id="selectedVarId" value={selectedVarId} onChange={e => setSelectedVarId(e.target.value)}>
                  {allVariants.map(v => (
                    <option key={v.id} value={v.id}>{v.productName} — {v.sku}</option>
                  ))}
                </select>
              </div>
              <div className="form-group">
                <label htmlFor="barcodeVal">Barcode Value</label>
                <input id="barcodeVal" value={barcodeVal} onChange={e => setBarcodeVal(e.target.value)} placeholder="e.g. 190198031203" required />
              </div>
              <div className="grid-2">
                <div className="form-group">
                  <label htmlFor="symbology">Symbology</label>
                  <select id="symbology" value={symbology} onChange={e => setSymbology(e.target.value)}>
                    <option value="upc_a">UPC-A</option>
                    <option value="upc_e">UPC-E</option>
                    <option value="ean_13">EAN-13</option>
                    <option value="code_128">Code 128</option>
                    <option value="qr">QR Code</option>
                  </select>
                </div>
                <div className="form-group">
                  <label htmlFor="source">Source</label>
                  <select id="source" value={source} onChange={e => setSource(e.target.value)}>
                    <option value="supplier">Supplier</option>
                    <option value="internal">Internal</option>
                    <option value="gs1">GS1 Registered</option>
                  </select>
                </div>
              </div>
              <div className="form-group" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <input type="checkbox" checked={isPrimary} onChange={e => setIsPrimary(e.target.checked)} style={{ width: 'auto', marginTop: 0 }} id="is_primary" />
                <label htmlFor="is_primary" style={{ margin: 0, cursor: 'pointer' }}>Make Primary Barcode</label>
              </div>
              <button type="submit" className="btn-primary" style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem' }} disabled={isAssigning} aria-busy={isAssigning}>
                {isAssigning && <Spinner />} {isAssigning ? 'Assigning...' : 'Assign Barcode'}
              </button>
            </form>
            {barcodeMsg && (
              <p role="alert" style={{ color: barcodeMsg === 'Barcode assigned successfully!' ? '#34d399' : '#f87171' }}>
                {barcodeMsg}
              </p>
            )}
          </div>

          {/* Barcode Lookup Finder */}
          <div className="card-lite">
            <div className="section-title">Barcode Scan Lookup</div>
            <form onSubmit={lookupBarcode} className="form-row">
              <div className="form-group" style={{ flex: 1, marginBottom: 0 }}>
                <label htmlFor="lookupVal">Scan / Type Code</label>
                <input id="lookupVal" value={lookupVal} onChange={e => setLookupVal(e.target.value)} placeholder="Scan barcode..." required />
              </div>
              <button type="submit" className="btn-primary" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem' }} disabled={isResolving} aria-busy={isResolving}>
                {isResolving && <Spinner />} {isResolving ? 'Resolving...' : 'Resolve'}
              </button>
            </form>
            {lookupResult && (
              <div role="alert" className="text-success" style={{ marginTop: '1rem', fontSize: '0.95rem' }}>
                ✓ Resolved Variant ID: <strong>{lookupResult}</strong>
              </div>
            )}
            {lookupError && (
              <div role="alert" className="text-danger" style={{ marginTop: '1rem', fontSize: '0.95rem' }}>
                ✗ Error: {lookupError}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
