import { combineReducers } from "redux";

// Front
import APIKeyReducer from "./apiKey/reducer";
import ForgetPasswordReducer from "./auth/forgetpwd/reducer";
import LoginReducer from "./auth/login/reducer";

// Authentication
import ProfileReducer from "./auth/profile/reducer";
import AccountReducer from "./auth/register/reducer";

//Calendar
import CalendarReducer from "./calendar/reducer";
//Chat
import chatReducer from "./chat/reducer";
//Ecommerce

//Project

// Tasks

//Crypto

//TicketsList
//Crm
import CrmReducer from "./crm/reducer";
import CryptoReducer from "./crypto/reducer";

//Invoice

//Mailbox

// Dashboard Analytics
import DashboardAnalyticsReducer from "./dashboardAnalytics/reducer";

// Dashboard CRM
import DashboardCRMReducer from "./dashboardCRM/reducer";

// Dashboard Ecommerce
import DashboardCryptoReducer from "./dashboardCrypto/reducer";
import DashboardEcommerceReducer from "./dashboardEcommerce/reducer";

// Dashboard Cryto

// Dashboard Cryto
import DashboardJobReducer from "./dashboardJob/reducer";
import DashboardNFTReducer from "./dashboardNFT/reducer";
import DashboardProjectReducer from "./dashboardProject/reducer";

// Dashboard NFT

// Dashboard JOb
import EcommerceReducer from "./ecommerce/reducer";

// Pages > Team

// File Manager
import FileManagerReducer from "./fileManager/reducer";
import InvoiceReducer from "./invoice/reducer";

// To do

// Job
import JobReducer from "./jobs/reducer";
import LayoutReducer from "./layouts/reducer";
import MailboxReducer from "./mailbox/reducer";
import ProjectsReducer from "./projects/reducer";
import TasksReducer from "./tasks/reducer";
import TeamDataReducer from "./team/reducer";
import TicketsReducer from "./tickets/reducer";
import TodosReducer from "./todos/reducer";

// API Key

const rootReducer = combineReducers({
    Layout: LayoutReducer,
    Login: LoginReducer,
    Account: AccountReducer,
    ForgetPassword: ForgetPasswordReducer,
    Profile: ProfileReducer,
    Calendar: CalendarReducer,
    Chat: chatReducer,
    Projects: ProjectsReducer,
    Ecommerce: EcommerceReducer,
    Tasks: TasksReducer,
    Crypto: CryptoReducer,
    Tickets: TicketsReducer,
    Crm: CrmReducer,
    Invoice: InvoiceReducer,
    Mailbox: MailboxReducer,
    DashboardAnalytics: DashboardAnalyticsReducer,
    DashboardCRM: DashboardCRMReducer,
    DashboardEcommerce: DashboardEcommerceReducer,
    DashboardCrypto: DashboardCryptoReducer,
    DashboardProject: DashboardProjectReducer,
    DashboardNFT: DashboardNFTReducer,
    DashBoardJob: DashboardJobReducer,
    Team: TeamDataReducer,
    FileManager: FileManagerReducer,
    Todos: TodosReducer,
    Jobs: JobReducer,
    APIKey: APIKeyReducer
});

export default rootReducer;