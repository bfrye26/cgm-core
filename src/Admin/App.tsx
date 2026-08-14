import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AppLayout } from './components/AppLayout';
import { OverviewPage } from './routes/Overview';
import { SetupPage } from './routes/Setup';
import { QueriesPage } from './routes/Queries';
import { ContentPage } from './routes/Content';
import { RelationshipsPage } from './routes/Relationships';
import { DataPage } from './routes/Data';
import { ReportsPage } from './routes/Reports';
import { AutomationPage } from './routes/Automation';
import { BulkPage } from './routes/Bulk';
import { WorkflowPage } from './routes/Workflow';
import { SearchPage } from './routes/SearchPage';
import { GraphPage } from './routes/GraphPage';
import { InspectorPage } from './routes/Inspector';
import { ConfigurationPage } from './routes/Configuration';
import { DiagnosticsPage } from './routes/Diagnostics';

export default function App() {
  return (
    <HashRouter>
      <Routes>
        <Route element={<AppLayout />}>
          <Route index element={<Navigate to="/overview" replace />} />
          <Route path="overview" element={<OverviewPage />} />
          <Route path="setup" element={<SetupPage />} />
          <Route path="queries" element={<QueriesPage />} />
          <Route path="content" element={<ContentPage />} />
          <Route path="relationships" element={<RelationshipsPage />} />
          <Route path="data" element={<DataPage />} />
          <Route path="reports" element={<ReportsPage />} />
          <Route path="automation" element={<AutomationPage />} />
          <Route path="bulk" element={<BulkPage />} />
          <Route path="workflow" element={<WorkflowPage />} />
          <Route path="search" element={<SearchPage />} />
          <Route path="graph" element={<GraphPage />} />
          <Route path="inspector" element={<InspectorPage />} />
          <Route path="configuration" element={<ConfigurationPage />} />
          <Route path="diagnostics" element={<DiagnosticsPage />} />
          <Route path="*" element={<Navigate to="/overview" replace />} />
        </Route>
      </Routes>
    </HashRouter>
  );
}
